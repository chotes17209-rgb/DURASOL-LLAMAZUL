<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaCaja;
use App\Enums\EstadoLiquidacion;
use App\Enums\TipoChofer;
use App\Models\CajaMovimiento;
use App\Models\Chofer;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\LiquidacionItem;
use App\Models\Producto;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    /**
     * Hoja de liquidación diaria (equivale a la hoja "RESUMEN GNRAL" del Excel).
     */
    public function liquidacionDiaria(Request $request): View
    {
        $fecha = $request->date('fecha') ?? today()->subDay();
        $productos = Producto::activos()->get();

        $liquidaciones = Liquidacion::with(['chofer', 'vehiculo', 'items'])
            ->where('fecha_venta', $fecha->toDateString())
            ->where('estado', '!=', EstadoLiquidacion::Anulada)
            ->get();

        [$locales, $ruta] = $liquidaciones->partition(fn ($l) => $l->tipo !== TipoChofer::Ruta);

        // Detalle de ventas por precio unitario (columna derecha del Excel).
        $detallePrecios = LiquidacionItem::with('producto')
            ->whereIn('liquidacion_id', $liquidaciones->pluck('id'))
            ->selectRaw('producto_id, precio, SUM(cantidad) as cantidad, SUM(total) as total')
            ->groupBy('producto_id', 'precio')->orderBy('producto_id')->orderBy('precio')->get();

        $depositos = Deposito::with(['cuentaBancaria', 'chofer'])->where('fecha', $fecha->toDateString())->get();
        $gastosCaja = CajaMovimiento::where('fecha', $fecha->toDateString())->where('tipo', CajaMovimiento::EGRESO)
            ->whereNotIn('categoria', [CategoriaCaja::Deposito])->sum('monto');

        return view('reportes.liquidacion-diaria', compact('fecha', 'productos', 'locales', 'ruta', 'detallePrecios', 'depositos', 'gastosCaja'));
    }

    /** Detalle de ventas (equivale a la hoja "VENTAS" del Excel) con filtros. */
    public function ventas(Request $request)
    {
        $query = $this->ventasQuery($request);
        $totales = $this->sinColumnas($query)->selectRaw('SUM(liquidacion_items.cantidad) as cantidad, SUM(liquidacion_items.total) as total, SUM(liquidacion_items.monto_credito) as credito, SUM(liquidacion_items.vacios_devueltos) as vacios')->first();
        $porProducto = $this->sinColumnas($query)->join('productos', 'productos.id', '=', 'liquidacion_items.producto_id')
            ->selectRaw('productos.codigo, SUM(liquidacion_items.cantidad) as cantidad, SUM(liquidacion_items.total) as total')
            ->groupBy('productos.codigo')->orderBy('productos.codigo')->get();
        $items = $query->with(['liquidacion.chofer', 'cliente', 'producto', 'empresa'])->paginate(50)->withQueryString();

        $filtros = [
            'choferes' => Chofer::vendedores()->pluck('alias', 'id'),
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
            'productos' => Producto::activos()->pluck('codigo', 'id'),
        ];

        return $this->tableOrPage($request, 'reportes.ventas', 'reportes._ventas', compact('items', 'totales', 'porProducto') + $filtros);
    }

    public function exportarVentas(Request $request): StreamedResponse
    {
        $query = $this->ventasQuery($request)->with(['liquidacion.chofer', 'liquidacion.vehiculo', 'cliente', 'producto', 'empresa']);
        AuditLogger::event('exportacion', 'Exportó el detalle de ventas a CSV', null, $request->only(['desde', 'hasta', 'chofer_id', 'empresa_id', 'producto_id']));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea los acentos
            fputcsv($out, ['FECHA', 'FECHA LIQUIDACION', 'LIQUIDACION', 'EMPRESA', 'CODIGO', 'PLACA', 'RESPONSABLE', 'CLIENTE', 'PRESENTACION', 'CANTIDAD', 'PRECIO', 'TOTAL', 'BALONES', 'CREDITO', 'METODO PAGO', 'CONTADO'], ';');
            $query->chunk(1000, function ($items) use ($out) {
                foreach ($items as $i) {
                    fputcsv($out, [
                        $i->liquidacion->fecha_venta->format('d/m/Y'), $i->liquidacion->fecha_liquidacion->format('d/m/Y'), $i->liquidacion->codigo,
                        $i->empresa?->nombre, $i->cliente?->codigo, $i->liquidacion->vehiculo?->placa, $i->liquidacion->chofer?->alias, $i->cliente?->nombre,
                        $i->producto?->codigo, $i->cantidad, $i->precio, $i->total, $i->vacios_devueltos, $i->monto_credito, $i->metodo_pago->label(), $i->montoPagado(),
                    ], ';');
                }
            });
            fclose($out);
        }, 'ventas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Caja por día (equivale a la hoja "CAJA GNRAL"):
     * General = Venta + Cobranza − Crédito − Gastos − FISE; Saldo = General − Depósitos.
     */
    public function cajaDiaria(Request $request): View
    {
        $desde = $request->date('desde') ?? today()->startOfMonth();
        $hasta = $request->date('hasta') ?? today();

        $liq = Liquidacion::where('estado', '!=', EstadoLiquidacion::Anulada)
            ->whereBetween('fecha_venta', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('fecha_venta as fecha, SUM(total_venta) as venta, SUM(total_cobranzas) as cobranza, SUM(total_credito) as credito, SUM(total_fises) as fise, SUM(total_gastos) as gastos, SUM(total_vouchers) as vouchers')
            ->groupBy('fecha_venta')->get()->keyBy(fn ($r) => Carbon::parse($r->fecha)->toDateString());

        $balones = LiquidacionItem::join('liquidaciones', 'liquidaciones.id', '=', 'liquidacion_items.liquidacion_id')
            ->where('liquidaciones.estado', '!=', EstadoLiquidacion::Anulada->value)
            ->whereNull('liquidaciones.deleted_at')
            ->whereBetween('liquidaciones.fecha_venta', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('liquidaciones.fecha_venta as fecha, SUM(liquidacion_items.cantidad) as cantidad')
            ->groupBy('liquidaciones.fecha_venta')->pluck('cantidad', 'fecha')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => $v]);

        $gastos = CajaMovimiento::where('tipo', CajaMovimiento::EGRESO)->whereNotIn('categoria', [CategoriaCaja::Deposito])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('fecha, SUM(monto) as total')->groupBy('fecha')->pluck('total', 'fecha')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => $v]);

        $depositos = Deposito::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('fecha, SUM(monto) as total')->groupBy('fecha')->pluck('total', 'fecha')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => $v]);

        $dias = [];
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            $k = $d->toDateString();
            $l = $liq[$k] ?? null;
            $venta = (float) ($l->venta ?? 0);
            $cobranza = (float) ($l->cobranza ?? 0);
            $credito = (float) ($l->credito ?? 0);
            $fise = (float) ($l->fise ?? 0);
            $gasto = (float) ($l->gastos ?? 0) + (float) ($gastos[$k] ?? 0);
            $general = $venta + $cobranza - $credito - $gasto - $fise;
            $deposito = (float) ($depositos[$k] ?? 0);
            $dias[] = [
                'fecha' => $d->copy(), 'balones' => (int) ($balones[$k] ?? 0), 'venta' => $venta, 'cobranza' => $cobranza, 'credito' => $credito,
                'gastos' => $gasto, 'fise' => $fise, 'vouchers' => (float) ($l->vouchers ?? 0), 'general' => $general, 'depositos' => $deposito, 'saldo' => $general - $deposito,
            ];
        }

        return view('reportes.caja-diaria', compact('dias', 'desde', 'hasta'));
    }

    /** Copia de la consulta sin columnas ni orden, para calcular agregados. */
    private function sinColumnas(Builder $query): Builder
    {
        $copia = (clone $query)->reorder();
        $copia->getQuery()->columns = null;

        return $copia;
    }

    private function ventasQuery(Request $request): Builder
    {
        return LiquidacionItem::query()
            ->select('liquidacion_items.*')
            ->join('liquidaciones', 'liquidaciones.id', '=', 'liquidacion_items.liquidacion_id')
            ->whereNull('liquidaciones.deleted_at')
            ->where('liquidaciones.estado', '!=', EstadoLiquidacion::Anulada->value)
            ->when($request->desde, fn ($q, $d) => $q->where('liquidaciones.fecha_venta', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->where('liquidaciones.fecha_venta', '<=', $h))
            ->when($request->chofer_id, fn ($q, $c) => $q->where('liquidaciones.chofer_id', $c))
            ->when($request->empresa_id, fn ($q, $e) => $q->where('liquidacion_items.empresa_id', $e))
            ->when($request->producto_id, fn ($q, $p) => $q->where('liquidacion_items.producto_id', $p))
            ->when($request->q, fn ($q, $t) => $q->whereHas('cliente', fn ($c) => $c->buscar($t)))
            ->when($request->solo_credito, fn ($q) => $q->where('liquidacion_items.monto_credito', '>', 0))
            ->orderByDesc('liquidaciones.fecha_venta')->orderByDesc('liquidacion_items.id');
    }
}
