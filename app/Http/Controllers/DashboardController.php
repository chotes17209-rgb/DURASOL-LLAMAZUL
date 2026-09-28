<?php

namespace App\Http\Controllers;

use App\Enums\EstadoLiquidacion;
use App\Models\CuentaPorCobrar;
use App\Models\Liquidacion;
use App\Models\LiquidacionItem;
use App\Models\Parte;
use App\Models\Vehiculo;
use App\Services\AlmacenService;
use App\Services\CajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AlmacenService $almacen, CajaService $caja): View
    {
        // Referencia: el último día con ventas (la liquidación es del día anterior).
        $ultimaVenta = Liquidacion::where('estado', '!=', EstadoLiquidacion::Anulada)->max('fecha_venta');
        $fecha = $request->date('fecha') ?? ($ultimaVenta ? Carbon::parse($ultimaVenta) : today());
        $inicioMes = $fecha->copy()->startOfMonth();

        $liqValidas = fn () => Liquidacion::where('estado', '!=', EstadoLiquidacion::Anulada);
        $ventaDia = (float) $liqValidas()->where('fecha_venta', $fecha->toDateString())->sum('total_venta');
        $ventaMes = (float) $liqValidas()->whereBetween('fecha_venta', [$inicioMes->toDateString(), $fecha->toDateString()])->sum('total_venta');

        $itemsBase = fn () => LiquidacionItem::join('liquidaciones', 'liquidaciones.id', '=', 'liquidacion_items.liquidacion_id')
            ->whereNull('liquidaciones.deleted_at')->where('liquidaciones.estado', '!=', EstadoLiquidacion::Anulada->value);

        $balonesDia = (int) $itemsBase()->where('liquidaciones.fecha_venta', $fecha->toDateString())->sum('liquidacion_items.cantidad');
        $balonesMes = (int) $itemsBase()->whereBetween('liquidaciones.fecha_venta', [$inicioMes->toDateString(), $fecha->toDateString()])->sum('liquidacion_items.cantidad');

        // Ventas de los últimos 30 días (gráfico).
        $desde30 = $fecha->copy()->subDays(29);
        $serie = $liqValidas()->whereBetween('fecha_venta', [$desde30->toDateString(), $fecha->toDateString()])
            ->selectRaw('fecha_venta, SUM(total_venta) as total')->groupBy('fecha_venta')->pluck('total', 'fecha_venta')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => (float) $v]);
        $labels = [];
        $valores = [];
        for ($d = $desde30->copy(); $d->lte($fecha); $d->addDay()) {
            $labels[] = $d->format('d/m');
            $valores[] = round($serie[$d->toDateString()] ?? 0, 2);
        }

        $porProducto = $itemsBase()->join('productos', 'productos.id', '=', 'liquidacion_items.producto_id')
            ->whereBetween('liquidaciones.fecha_venta', [$inicioMes->toDateString(), $fecha->toDateString()])
            ->selectRaw('productos.codigo, SUM(liquidacion_items.cantidad) as cantidad, SUM(liquidacion_items.total) as total')
            ->groupBy('productos.codigo')->orderByDesc('cantidad')->get();

        $topChoferes = $itemsBase()->join('choferes', 'choferes.id', '=', 'liquidaciones.chofer_id')
            ->whereBetween('liquidaciones.fecha_venta', [$inicioMes->toDateString(), $fecha->toDateString()])
            ->selectRaw('choferes.alias, SUM(liquidacion_items.cantidad) as cantidad, SUM(liquidacion_items.total) as total')
            ->groupBy('choferes.alias')->orderByDesc('total')->limit(8)->get();

        $graficoVentas = [
            'type' => 'bar',
            'data' => ['labels' => $labels, 'datasets' => [['label' => 'Venta (S/)', 'data' => $valores, 'backgroundColor' => '#1a3a80', 'borderRadius' => 0, 'maxBarThickness' => 26]]],
            'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]],
                'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['grid' => ['color' => '#eef2f7'], 'beginAtZero' => true]]],
        ];
        $graficoProductos = [
            'type' => 'doughnut',
            'data' => ['labels' => $porProducto->pluck('codigo'), 'datasets' => [['data' => $porProducto->pluck('cantidad'),
                'backgroundColor' => ['#1a3a80', '#e07a1f', '#4f78c4', '#7a8699', '#2f855a', '#b3c6ea', '#c05621', '#cbd5e1'], 'borderWidth' => 0]]],
            'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'cutout' => '60%', 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];

        // Alertas operativas.
        $vehiculos = Vehiculo::with('documentos')->operativos()->get();
        $alertasDocs = [];
        foreach ($vehiculos as $v) {
            foreach ($v->estadoDocumentos() as $info) {
                if (in_array($info['estado'], ['vencido', 'por_vencer', 'sin_registro'], true)) {
                    $alertasDocs[] = ['vehiculo' => $v, 'label' => $info['label'], 'estado' => $info['estado'], 'documento' => $info['documento']];
                }
            }
        }
        $docsCriticos = collect($alertasDocs)->whereIn('estado', ['vencido', 'por_vencer'])->count();

        return view('dashboard', [
            'fecha' => $fecha,
            'ventaDia' => $ventaDia,
            'ventaMes' => $ventaMes,
            'balonesDia' => $balonesDia,
            'balonesMes' => $balonesMes,
            'porCobrar' => (float) CuentaPorCobrar::pendientes()->sum('saldo'),
            'saldoCaja' => $caja->saldoAl(today()),
            'stock' => $almacen->controlDelDia(today()),
            'parteHoy' => Parte::where('fecha', today()->toDateString())->first(),
            'porProducto' => $porProducto,
            'topChoferes' => $topChoferes,
            'graficoVentas' => $graficoVentas,
            'graficoProductos' => $graficoProductos,
            'alertasDocs' => collect($alertasDocs)->sortBy(fn ($a) => ['vencido' => 0, 'por_vencer' => 1, 'sin_registro' => 2][$a['estado']])->take(8),
            'docsCriticos' => $docsCriticos,
            'borradores' => Liquidacion::where('estado', EstadoLiquidacion::Borrador)->count(),
        ]);
    }
}
