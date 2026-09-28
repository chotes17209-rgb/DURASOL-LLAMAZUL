<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Services\RentabilidadService;
use App\Support\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Rentabilidad del periodo: estado de resultados, márgenes y compras en planta frente a ventas. */
class RentabilidadController extends Controller
{
    public function __construct(private readonly RentabilidadService $rentabilidad) {}

    public function index(Request $request)
    {
        [$desde, $hasta] = $this->periodo($request);
        $empresaId = $request->integer('empresa_id') ?: null;
        $r = $this->rentabilidad->reporte($desde, $hasta, $empresaId);
        $empresa = $empresaId ? Empresa::find($empresaId)?->nombre : null;

        if (in_array($request->formato, ['pdf', 'xlsx'], true)) {
            return $this->reporte($r, $empresa)->descargar($request->formato, 'rentabilidad-'.$desde->format('Y-m-d').'-'.$hasta->format('Y-m-d'));
        }

        return view('reportes.rentabilidad', $r + [
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
            'empresa' => $empresa,
            'mes' => $request->input('mes', $desde->format('Y-m')),
        ]);
    }

    /** Por defecto el mes en curso; «mes» (AAAA-MM) o un rango «desde/hasta». */
    private function periodo(Request $request): array
    {
        if ($request->filled('desde')) {
            $desde = $request->date('desde');
            $hasta = $request->date('hasta') ?? $desde->copy()->endOfMonth();

            return [$desde, $hasta->lt($desde) ? $desde->copy() : $hasta];
        }
        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->mes) ? Carbon::parse($request->mes.'-01') : today()->startOfMonth();

        return [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()->min(today())->max($mes->copy()->startOfMonth())];
    }

    private function reporte(array $r, ?string $empresa): Reporte
    {
        $res = $r['resultado'];
        $periodo = 'Del '.$r['desde']->format('d/m/Y').' al '.$r['hasta']->format('d/m/Y').($empresa ? ' · '.$empresa : '');
        $estado = [
            ['Ventas netas', $res['ventas'], 100],
            ['(−) Costo de ventas', -$res['costo'], $res['ventas'] ? round(-$res['costo'] / $res['ventas'] * 100, 2) : 0],
            ['UTILIDAD BRUTA', $res['utilidad_bruta'], $res['margen_bruto']],
        ];
        foreach ($res['gastos'] as $g) {
            $estado[] = ['(−) '.$g['grupo'].': '.$g['concepto'], -$g['monto'], $res['ventas'] ? round(-$g['monto'] / $res['ventas'] * 100, 2) : 0];
        }
        if ($res['otros_ingresos']) {
            $estado[] = ['(+) Otros ingresos', $res['otros_ingresos'], null];
        }

        $reporte = (new Reporte('Rentabilidad', $periodo, true))
            ->datos(['Periodo' => $periodo, 'Balones vendidos' => number_format($res['balones']), 'Traslados sin valor' => number_format($res['traslados'])])
            ->tabla('Estado de resultados', ['Concepto' => 'texto', 'Importe (S/)' => 'decimal', '% sobre ventas' => 'decimal'], $estado,
                ['UTILIDAD OPERATIVA', $res['utilidad_operativa'], $res['margen_operativo']])
            ->tabla('Rentabilidad por presentación', ['Producto' => 'texto', 'Cantidad' => 'entero', 'Precio prom.' => 'decimal', 'Costo prom.' => 'decimal',
                'Ventas' => 'decimal', 'Costo de ventas' => 'decimal', 'Utilidad' => 'decimal', 'Margen %' => 'decimal', 'Utilidad x unidad' => 'decimal'],
                $r['por_producto']->map(fn ($f) => [$f['producto']->codigo.' · '.$f['producto']->nombre, $f['cantidad'], $f['precio_promedio'], $f['costo_promedio'],
                    $f['ventas'], $f['costo'], $f['utilidad'], $f['margen'], $f['utilidad_unitaria']]),
                ['TOTAL', $res['balones'], '', '', $res['ventas'], $res['costo'], $res['utilidad_bruta'], $res['margen_bruto'], ''])
            ->tabla('Por empresa', ['Empresa' => 'texto', 'Cantidad' => 'entero', 'Ventas' => 'decimal', 'Costo' => 'decimal', 'Utilidad' => 'decimal', 'Margen %' => 'decimal'],
                $r['por_empresa']->map(fn ($f, $k) => [$k, $f['cantidad'], $f['ventas'], $f['costo'], $f['utilidad'], $f['margen']])->values());

        $codigos = $r['productos']->pluck('codigo', 'id');
        $colsDia = ['Fecha' => 'texto'];
        foreach ($codigos as $c) {
            $colsDia[$c] = 'entero';
        }
        $colsDia += ['Importe total' => 'decimal', 'Utilidad' => 'decimal', 'Margen %' => 'decimal'];
        $reporte->tabla('Rentabilidad diaria', $colsDia, $r['por_dia']->map(fn ($d) => array_merge([$d['fecha']->format('d/m/Y')],
            $codigos->map(fn ($c, $id) => $d['cantidades'][$id] ?? 0)->values()->all(), [$d['ventas'], $d['utilidad'], $d['margen']])),
            array_merge(['TOTAL'], $codigos->map(fn ($c, $id) => $r['por_dia']->sum(fn ($d) => $d['cantidades'][$id] ?? 0))->values()->all(),
                [$res['ventas'], $res['utilidad_bruta'], $res['margen_bruto']]));

        $reporte->tabla('Por responsable', ['Responsable' => 'texto', 'Balones' => 'entero', 'Ventas' => 'decimal', 'Utilidad' => 'decimal', 'Margen %' => 'decimal', 'Utilidad x balón' => 'decimal'],
            $r['por_responsable']->map(fn ($f, $k) => [$k, $f['cantidad'], $f['ventas'], $f['utilidad'], $f['margen'], $f['utilidad_unitaria']])->values());

        return $reporte->tabla('Compras en planta frente a ventas', ['Empresa' => 'texto', 'Producto' => 'texto', 'Comprado' => 'entero', 'Vendido' => 'entero', 'Diferencia' => 'entero'],
            array_map(fn ($c) => [$c['empresa'], $c['codigo'], $c['compras'], $c['ventas'], $c['diferencia']], $r['compras']['resumen']),
            null, 'Vendido incluye traslados a precio 0. Diferencia positiva: queda en stock; negativa: se vendió stock anterior.');
    }
}
