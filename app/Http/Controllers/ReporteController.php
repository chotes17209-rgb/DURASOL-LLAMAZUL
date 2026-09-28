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
use App\Support\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReporteController extends Controller
{
    /**
     * Hoja de liquidación diaria (hoja "RESUMEN GNRAL" del Excel):
     *  - Ventas liquidadas del día (reparto local y almacén, por fecha de venta).
     *  - Liquidaciones de ruta que se liquidan ese día (por fecha de liquidación).
     *  Por depositar = venta total + cobranza − crédito − varios − FISE − vouchers − depósitos.
     */
    public function liquidacionDiaria(Request $request)
    {
        $fecha = $request->date('fecha') ?? today()->subDay();
        $d = $this->datosLiquidacionDiaria($fecha);

        if ($formato = $this->formato($request)) {
            $cols = ['Placa' => 'texto', 'Responsable' => 'texto'];
            foreach ($d['productos'] as $p) {
                $cols[$p->codigo] = 'entero';
            }
            $cols += ['Venta total' => 'decimal', 'Cobranza' => 'decimal', 'Crédito' => 'decimal', 'Varios' => 'decimal', 'FISE' => 'decimal', 'Vouchers' => 'decimal', 'Depósitos' => 'decimal', 'Por depositar' => 'decimal'];
            $claves = ['venta', 'cobranza', 'credito', 'varios', 'fise', 'vouchers', 'depositos', 'por_depositar'];
            $fila = fn (array $f) => array_merge([$f['placa'], $f['responsable']], array_values($f['cantidades']), array_map(fn ($k) => $f[$k], $claves));
            $total = fn (array $t) => array_merge(['TOTALES', ''], array_values($t['cantidades']), array_map(fn ($k) => $t[$k], $claves));

            $reporte = (new Reporte('Hoja de liquidación diaria', ucfirst($fecha->translatedFormat('l d \d\e F \d\e Y')), true));
            foreach ($d['grupos'] as $grupo) {
                $reporte->tabla($grupo['titulo'], $cols, array_map($fila, $grupo['filas']), $total($grupo['total']));
            }
            $reporte->tabla('Por depositar por responsable', ['Responsable' => 'texto', 'Importe' => 'decimal'], collect($d['porDepositar'])->map(fn ($v, $k) => [$k, $v])->values(), ['TOTAL', array_sum($d['porDepositar'])])
                ->tabla('Depósitos realizados', ['Fecha' => 'texto', 'Responsable' => 'texto', 'Banco / cuenta' => 'texto', 'Empresa' => 'texto', 'Quién depositó' => 'texto', 'Importe' => 'decimal'],
                    $d['depositos']->map(fn ($x) => [fecha($x->fecha), $x->chofer?->alias, $x->cuentaBancaria?->nombreMostrar(), $x->empresa?->nombre, $x->depositante, $x->monto]), ['TOTAL', '', '', '', '', $d['depositos']->sum('monto')])
                ->tabla('Detalle de ventas por precio', ['Producto' => 'texto', 'Cantidad' => 'entero', 'P.U.' => 'decimal', 'Total' => 'decimal'],
                    $d['detallePrecios']->map(fn ($x) => [$x->producto?->codigo, $x->cantidad, $x->precio, $x->total]), ['TOTAL', $d['detallePrecios']->sum('cantidad'), '', $d['detallePrecios']->sum('total')]);

            return $reporte->descargar($formato, 'liquidacion-diaria-'.$fecha->toDateString());
        }

        return view('reportes.liquidacion-diaria', ['fecha' => $fecha] + $d);
    }

    /** Arma la hoja de liquidación diaria (se usa en pantalla, PDF y Excel). */
    private function datosLiquidacionDiaria(Carbon $fecha): array
    {
        $productos = Producto::activos()->whereIn('tipo', ['gas', 'envase'])->get();
        $base = fn () => Liquidacion::with(['chofer', 'vehiculo', 'items'])->where('estado', '!=', EstadoLiquidacion::Anulada)->orderBy('id');

        $locales = $base()->where('fecha_venta', $fecha->toDateString())->where('tipo', '!=', TipoChofer::Ruta->value)->get();
        $ruta = $base()->where('fecha_liquidacion', $fecha->toDateString())->where('tipo', TipoChofer::Ruta->value)->get();

        // Depósitos bancarios del día por responsable (se descuentan una sola vez por chofer).
        $depositos = Deposito::with(['cuentaBancaria', 'chofer', 'empresa'])->where('fecha', $fecha->toDateString())->orderBy('id')->get();
        $pendienteDeposito = $depositos->whereNotNull('chofer_id')->groupBy('chofer_id')->map->sum('monto')->all();

        $armar = function ($liquidaciones) use ($productos, &$pendienteDeposito) {
            $filas = $liquidaciones->map(function (Liquidacion $l) use ($productos, &$pendienteDeposito) {
                $depositado = (float) ($pendienteDeposito[$l->chofer_id] ?? 0);
                unset($pendienteDeposito[$l->chofer_id]);
                $cantidades = $productos->mapWithKeys(fn ($p) => [$p->codigo => (int) $l->items->where('producto_id', $p->id)->sum('cantidad')])->all();

                return [
                    'liquidacion' => $l,
                    'placa' => $l->vehiculo?->placa ?? 'LOCAL',
                    'responsable' => $l->chofer?->alias,
                    'fecha_venta' => $l->fecha_venta,
                    'cantidades' => $cantidades,
                    'venta' => (float) $l->total_venta,
                    'cobranza' => (float) $l->total_cobranzas,
                    'credito' => (float) $l->total_credito,
                    'varios' => (float) $l->total_gastos,
                    'fise' => (float) $l->total_fises,
                    'vouchers' => (float) $l->total_vouchers,
                    'depositos' => $depositado,
                    'por_depositar' => round((float) $l->efectivo_esperado - $depositado, 2),
                ];
            })->all();
            $total = ['cantidades' => $productos->mapWithKeys(fn ($p) => [$p->codigo => array_sum(array_map(fn ($f) => $f['cantidades'][$p->codigo], $filas))])->all()];
            foreach (['venta', 'cobranza', 'credito', 'varios', 'fise', 'vouchers', 'depositos', 'por_depositar'] as $k) {
                $total[$k] = round(array_sum(array_column($filas, $k)), 2);
            }

            return ['filas' => $filas, 'total' => $total];
        };

        $grupos = [
            ['titulo' => 'Detalle de ventas liquidadas (reparto local y almacén)'] + $armar($locales),
            ['titulo' => 'Liquidaciones de ruta liquidadas este día'] + $armar($ruta),
        ];

        $porDepositar = [];
        foreach ($grupos as $g) {
            foreach ($g['filas'] as $f) {
                $porDepositar[$f['responsable']] = round(($porDepositar[$f['responsable']] ?? 0) + $f['por_depositar'], 2);
            }
        }

        $ids = $locales->pluck('id')->merge($ruta->pluck('id'));
        $detallePrecios = LiquidacionItem::with('producto')->whereIn('liquidacion_id', $ids)
            ->selectRaw('producto_id, precio, SUM(cantidad) as cantidad, SUM(total) as total')
            ->groupBy('producto_id', 'precio')->orderBy('producto_id')->orderByDesc('precio')->get();

        return [
            'productos' => $productos,
            'grupos' => $grupos,
            'porDepositar' => $porDepositar,
            'depositos' => $depositos,
            'detallePrecios' => $detallePrecios,
            'gastosCaja' => (float) CajaMovimiento::where('fecha', $fecha->toDateString())->where('tipo', CajaMovimiento::EGRESO)
                ->whereNotIn('categoria', [CategoriaCaja::Deposito])->sum('monto'),
        ];
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

    /** Detalle de ventas en Excel (todas las filas) o PDF (resumen + hasta 1 500 filas). */
    public function exportarVentas(Request $request)
    {
        $formato = $this->formato($request) ?? 'xlsx';
        $query = $this->ventasQuery($request)->with(['liquidacion.chofer', 'liquidacion.vehiculo', 'cliente', 'producto', 'empresa']);
        $total = (clone $query)->count();
        $limite = $formato === 'pdf' ? 1500 : 60000;
        $items = $query->limit($limite)->get();

        $periodo = 'Del '.($request->desde ? fecha($request->desde) : 'inicio').' al '.($request->hasta ? fecha($request->hasta) : fecha(today()));
        $reporte = new Reporte('Detalle de ventas', $periodo, true);
        $reporte->datos(array_filter([
            'Chofer' => $request->chofer_id ? Chofer::find($request->chofer_id)?->alias : null,
            'Empresa' => $request->empresa_id ? Empresa::find($request->empresa_id)?->nombre : null,
            'Producto' => $request->producto_id ? Producto::find($request->producto_id)?->codigo : null,
            'Registros' => num($total),
        ]));

        $porProducto = $items->groupBy(fn ($i) => $i->producto?->codigo)->map(fn ($g, $k) => [$k, $g->sum('cantidad'), $g->sum('total'), $g->sum('monto_credito')])->sortKeys()->values();
        $reporte->tabla('Resumen por producto', ['Producto' => 'texto', 'Cantidad' => 'entero', 'Importe' => 'decimal', 'Crédito' => 'decimal'], $porProducto,
            ['TOTAL', $items->sum('cantidad'), $items->sum('total'), $items->sum('monto_credito')]);

        $reporte->tabla('Detalle', [
            'Fecha' => 'texto', 'F. liquid.' => 'texto', 'Empresa' => 'texto', 'Código' => 'texto', 'Placa' => 'texto', 'Responsable' => 'texto', 'Cliente' => 'texto',
            'Pres.' => 'texto', 'Cant.' => 'entero', 'Precio' => 'decimal', 'Total' => 'decimal', 'Balones dev.' => 'entero', 'Crédito' => 'decimal', 'Pago' => 'texto', 'Contado' => 'decimal',
        ], $items->map(fn ($i) => [
            $i->liquidacion->fecha_venta->format('d/m/Y'), $i->liquidacion->fecha_liquidacion?->format('d/m/Y'), $i->empresa?->nombre, $i->cliente?->codigo,
            $i->liquidacion->vehiculo?->placa, $i->liquidacion->chofer?->alias, $i->cliente?->nombre, $i->producto?->codigo, $i->cantidad, $i->precio, $i->total,
            $i->vacios_devueltos, $i->monto_credito, $i->metodo_pago->label(), $i->montoPagado(),
        ]), null, $total > $limite ? 'Se muestran las primeras '.num($limite).' de '.num($total).' filas. Descarga en Excel o filtra por fechas para ver todo.' : null);

        return $reporte->descargar($formato, 'ventas-'.now()->format('Ymd-His'));
    }

    /**
     * Caja por día (equivale a la hoja "CAJA GNRAL"):
     * General = Venta + Cobranza − Crédito − Gastos − FISE; Saldo = General − Depósitos.
     */
    public function cajaDiaria(Request $request)
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

        if ($formato = $this->formato($request)) {
            $t = fn ($k) => array_sum(array_column($dias, $k));
            $cols = ['Fecha' => 'texto', 'Balones' => 'entero', 'Importe total' => 'decimal', 'Cobranza' => 'decimal', 'Crédito' => 'decimal', 'Gastos' => 'decimal',
                'FISE' => 'decimal', 'Vouchers' => 'decimal', 'General' => 'decimal', 'Depósitos' => 'decimal', 'Saldo' => 'decimal'];
            $claves = ['balones', 'venta', 'cobranza', 'credito', 'gastos', 'fise', 'vouchers', 'general', 'depositos', 'saldo'];

            return (new Reporte('Caja general por día', 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y'), true))
                ->tabla(null, $cols, array_map(fn ($d) => array_merge([$d['fecha']->translatedFormat('D d/m/Y')], array_map(fn ($k) => $d[$k], $claves)), $dias),
                    array_merge(['TOTAL'], array_map($t, $claves)), 'General = venta + cobranza − crédito − gastos − FISE. Saldo = general − depósitos.')
                ->descargar($formato, 'caja-'.$desde->toDateString().'-al-'.$hasta->toDateString());
        }

        return view('reportes.caja-diaria', compact('dias', 'desde', 'hasta'));
    }

    /** Formato de descarga pedido (?formato=pdf|xlsx) o null para ver en pantalla. */
    private function formato(Request $request): ?string
    {
        return in_array($request->formato, ['pdf', 'xlsx'], true) ? $request->formato : null;
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
