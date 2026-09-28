<?php

namespace App\Services;

use App\Enums\CategoriaCaja;
use App\Enums\EstadoLiquidacion;
use App\Models\CajaChicaMovimiento;
use App\Models\CajaMovimiento;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rentabilidad del periodo (estado de resultados de gestión).
 *
 *   Ventas netas                 Σ importe de las ventas valorizadas (precio > 0)
 * − Costo de ventas              Σ cantidad × costo unitario a la fecha de venta
 * = Utilidad bruta               (margen bruto = utilidad bruta / ventas)
 * − Gastos operativos            varios de liquidación + gastos de caja chica + gastos, planilla y otros de caja general
 * + Otros ingresos               ingresos de caja general no provenientes de ventas
 * = Utilidad operativa           (margen operativo)
 *
 * Notas de criterio:
 *  - Las salidas a precio 0 (traslados a almacenes de ruta, bonificaciones) no son venta: no suman
 *    ingreso ni costo; se informan por separado (así lo hace la hoja RENTABILIDAD del Excel).
 *  - Los vales FISE, créditos, cobranzas y depósitos son medios de cobro o movimientos de tesorería:
 *    no afectan el resultado.
 *  - La reposición de caja chica es una transferencia entre cajas: el gasto se reconoce cuando
 *    caja chica lo paga, una sola vez.
 */
class RentabilidadService
{
    public function reporte(Carbon $desde, Carbon $hasta, ?int $empresaId = null): array
    {
        $productos = Producto::orderBy('orden')->get()->keyBy('id');
        $lineas = $this->lineas($desde, $hasta, $empresaId);
        $valorizadas = $lineas->where('total', '>', 0);

        $ventas = round((float) $valorizadas->sum('total'), 2);
        $costo = round((float) $valorizadas->sum('costo'), 2);
        $utilidadBruta = round($ventas - $costo, 2);
        $gastos = $this->gastos($desde, $hasta, $empresaId);
        $otrosIngresos = $empresaId ? 0.0 : round((float) CajaMovimiento::where('tipo', CajaMovimiento::INGRESO)
            ->where('categoria', CategoriaCaja::Otro)->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->sum('monto'), 2);
        $totalGastos = round(array_sum(array_column($gastos, 'monto')), 2);
        $utilidadOperativa = round($utilidadBruta - $totalGastos + $otrosIngresos, 2);

        return [
            'desde' => $desde, 'hasta' => $hasta,
            'resultado' => [
                'ventas' => $ventas,
                'costo' => $costo,
                'utilidad_bruta' => $utilidadBruta,
                'margen_bruto' => $this->margen($utilidadBruta, $ventas),
                'gastos' => $gastos,
                'total_gastos' => $totalGastos,
                'otros_ingresos' => $otrosIngresos,
                'utilidad_operativa' => $utilidadOperativa,
                'margen_operativo' => $this->margen($utilidadOperativa, $ventas),
                'balones' => (int) $valorizadas->sum('cantidad'),
                'traslados' => (int) $lineas->where('total', '<=', 0)->sum('cantidad'),
                'sin_costo' => round((float) $valorizadas->whereNull('costo_unitario')->sum('total'), 2),
            ],
            'productos' => $productos->values(),
            'por_producto' => $this->agrupar($valorizadas, fn ($l) => $l->producto_id, $lineas)
                ->map(fn ($f, $id) => $f + ['producto' => $productos[$id]])->sortBy(fn ($f) => $f['producto']->orden)->values(),
            'por_empresa' => $this->agrupar($valorizadas, fn ($l) => $l->empresa, $lineas)->sortKeys(),
            'por_dia' => $this->porDia($lineas, $productos),
            'por_responsable' => $this->agrupar($valorizadas, fn ($l) => $l->responsable, $lineas)
                ->map(fn ($f, $k) => $f + ['cantidades' => $valorizadas->where('responsable', $k)->groupBy('producto_id')->map->sum('cantidad')])
                ->sortByDesc('utilidad'),
            'compras' => $this->comprasVsVentas($desde, $hasta, $empresaId, $lineas),
        ];
    }

    /** Líneas de venta del periodo con su costo (liquidaciones no anuladas, por fecha de venta). */
    private function lineas(Carbon $desde, Carbon $hasta, ?int $empresaId): Collection
    {
        return DB::table('liquidacion_items as i')
            ->join('liquidaciones as l', 'l.id', '=', 'i.liquidacion_id')
            ->join('choferes as c', 'c.id', '=', 'l.chofer_id')
            ->join('empresas as e', 'e.id', '=', 'i.empresa_id')
            ->whereNull('l.deleted_at')
            ->where('l.estado', '!=', EstadoLiquidacion::Anulada->value)
            ->whereBetween('l.fecha_venta', [$desde->toDateString(), $hasta->toDateString()])
            ->when($empresaId, fn ($q) => $q->where('i.empresa_id', $empresaId))
            ->select('l.fecha_venta', 'i.producto_id', 'i.cantidad', 'i.total', 'i.costo_unitario', 'e.nombre as empresa', 'c.alias as responsable')
            ->get()
            ->map(function ($l) {
                $l->fecha_venta = substr((string) $l->fecha_venta, 0, 10);
                $l->total = (float) $l->total;
                $l->costo = $l->costo_unitario === null ? 0.0 : round($l->cantidad * (float) $l->costo_unitario, 2);

                return $l;
            });
    }

    private function agrupar(Collection $valorizadas, callable $clave, Collection $todas): Collection
    {
        $traslados = $todas->where('total', '<=', 0)->groupBy($clave)->map->sum('cantidad');

        return $valorizadas->groupBy($clave)->map(function (Collection $g, $k) use ($traslados) {
            $ventas = round((float) $g->sum('total'), 2);
            $costo = round((float) $g->sum('costo'), 2);
            $cantidad = (int) $g->sum('cantidad');
            $utilidad = round($ventas - $costo, 2);

            return [
                'cantidad' => $cantidad,
                'traslados' => (int) ($traslados[$k] ?? 0),
                'ventas' => $ventas,
                'costo' => $costo,
                'utilidad' => $utilidad,
                'margen' => $this->margen($utilidad, $ventas),
                'precio_promedio' => $cantidad ? round($ventas / $cantidad, 2) : 0,
                'costo_promedio' => $cantidad ? round($costo / $cantidad, 2) : 0,
                'utilidad_unitaria' => $cantidad ? round($utilidad / $cantidad, 2) : 0,
                'sin_costo' => $g->whereNull('costo_unitario')->isNotEmpty(),
            ];
        });
    }

    private function porDia(Collection $lineas, Collection $productos): Collection
    {
        return $lineas->groupBy('fecha_venta')->sortKeys()->map(function (Collection $g, $fecha) {
            $valorizadas = $g->where('total', '>', 0);
            $ventas = round((float) $valorizadas->sum('total'), 2);
            $utilidad = round($ventas - (float) $valorizadas->sum('costo'), 2);

            return [
                'fecha' => Carbon::parse($fecha),
                'cantidades' => $valorizadas->groupBy('producto_id')->map->sum('cantidad'),
                'ventas' => $ventas,
                'costo' => round((float) $valorizadas->sum('costo'), 2),
                'utilidad' => $utilidad,
                'margen' => $this->margen($utilidad, $ventas),
            ];
        })->values();
    }

    /** @return array<int, array{grupo: string, concepto: string, monto: float}> */
    private function gastos(Carbon $desde, Carbon $hasta, ?int $empresaId): array
    {
        $rango = [$desde->toDateString(), $hasta->toDateString()];
        $gastos = [];

        $varios = (float) Liquidacion::where('estado', '!=', EstadoLiquidacion::Anulada)->whereBetween('fecha_venta', $rango)->sum('total_gastos');
        if ($empresaId) {
            // Los gastos no se asignan por empresa: se muestran solo en el consolidado.
            return [];
        }
        $gastos[] = ['grupo' => 'Gastos de reparto', 'concepto' => 'Varios de choferes (liquidaciones)', 'monto' => round($varios, 2)];

        CajaChicaMovimiento::where('tipo', CajaChicaMovimiento::GASTO)->whereBetween('fecha', $rango)
            ->selectRaw('concepto, SUM(monto) as total')->groupBy('concepto')->orderByDesc('total')->get()
            ->each(function ($g) use (&$gastos) {
                $gastos[] = ['grupo' => 'Caja chica', 'concepto' => $g->concepto, 'monto' => round((float) $g->total, 2)];
            });

        CajaMovimiento::where('tipo', CajaMovimiento::EGRESO)
            ->whereIn('categoria', [CategoriaCaja::Gasto, CategoriaCaja::Planilla, CategoriaCaja::Otro])
            ->whereBetween('fecha', $rango)->selectRaw('categoria, SUM(monto) as total')->groupBy('categoria')->get()
            ->each(function ($g) use (&$gastos) {
                $gastos[] = ['grupo' => 'Caja general', 'concepto' => $g->categoria->label(), 'monto' => round((float) $g->total, 2)];
            });

        return array_values(array_filter($gastos, fn ($g) => $g['monto'] > 0));
    }

    /**
     * Compras en planta frente a lo vendido (hoja STOCK): por empresa y presentación.
     * Compras = módulo de compras (registro manual, parte diario sincronizado e importado).
     */
    private function comprasVsVentas(Carbon $desde, Carbon $hasta, ?int $empresaId, Collection $lineas): array
    {
        $rango = [$desde->toDateString(), $hasta->toDateString()];
        $codigos = ['S10' => 's10', 'S45' => 's45', 'M10' => 'm10'];
        $productos = Producto::whereIn('codigo', array_keys($codigos))->pluck('id', 'codigo');
        $empresas = Empresa::when($empresaId, fn ($q) => $q->whereKey($empresaId))->orderBy('nombre')->pluck('nombre', 'id');

        $compras = [];   // fecha => empresa => codigo => cantidad
        DB::table('compras_planta')->whereBetween('fecha', $rango)->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))->get()
            ->each(function ($c) use (&$compras, $productos) {
                $codigo = $productos->search($c->producto_id);
                $fecha = substr((string) $c->fecha, 0, 10);
                $compras[$fecha][$c->empresa_id][$codigo] = ($compras[$fecha][$c->empresa_id][$codigo] ?? 0) + $c->cantidad;
            });

        $empresaIds = $empresas->flip();
        $ventas = [];   // fecha => empresa_id => codigo => cantidad (incluye traslados: salen del stock)
        foreach ($lineas as $l) {
            $codigo = $productos->search($l->producto_id);
            if ($codigo && isset($empresaIds[$l->empresa])) {
                $e = $empresaIds[$l->empresa];
                $ventas[$l->fecha_venta][$e][$codigo] = ($ventas[$l->fecha_venta][$e][$codigo] ?? 0) + $l->cantidad;
            }
        }

        $resumen = [];
        foreach ($empresas as $id => $nombre) {
            foreach (array_keys($codigos) as $codigo) {
                $comprado = array_sum(array_map(fn ($d) => $d[$id][$codigo] ?? 0, $compras));
                $vendido = array_sum(array_map(fn ($d) => $d[$id][$codigo] ?? 0, $ventas));
                if ($comprado || $vendido) {
                    $resumen[] = ['empresa' => $nombre, 'codigo' => $codigo, 'compras' => $comprado, 'ventas' => $vendido, 'diferencia' => $comprado - $vendido];
                }
            }
        }

        // Consolidado por presentación (como la columna SALDO de la hoja STOCK): el vendido por una
        // empresa puede haberse comprado con la otra.
        if (! $empresaId && count($empresas) > 1) {
            $consolidado = collect($resumen)->groupBy('codigo')->map(fn ($g, $codigo) => [
                'empresa' => 'CONSOLIDADO', 'codigo' => $codigo, 'compras' => $g->sum('compras'), 'ventas' => $g->sum('ventas'),
                'diferencia' => $g->sum('compras') - $g->sum('ventas'), 'total' => true,
            ])->values()->all();
            $resumen = array_merge($consolidado, $resumen);
        }

        $fechas = array_unique(array_merge(array_keys($compras), array_keys($ventas)));
        sort($fechas);
        $diario = array_map(fn ($f) => [
            'fecha' => Carbon::parse($f),
            'compras' => collect(array_keys($codigos))->mapWithKeys(fn ($c) => [$c => array_sum(array_map(fn ($e) => $e[$c] ?? 0, $compras[$f] ?? []))])->all(),
            'ventas' => collect(array_keys($codigos))->mapWithKeys(fn ($c) => [$c => array_sum(array_map(fn ($e) => $e[$c] ?? 0, $ventas[$f] ?? []))])->all(),
        ], $fechas);

        return ['resumen' => $resumen, 'diario' => $diario, 'codigos' => array_keys($codigos)];
    }

    private function margen(float $utilidad, float $ventas): float
    {
        return $ventas > 0 ? round($utilidad / $ventas * 100, 2) : 0.0;
    }
}
