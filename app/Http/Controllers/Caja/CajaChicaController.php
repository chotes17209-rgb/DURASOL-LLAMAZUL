<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\CajaChicaRequest;
use App\Models\CajaChicaMovimiento;
use App\Models\Chofer;
use App\Models\Vehiculo;
use App\Services\CajaChicaService;
use App\Support\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Caja chica: gastos menores con comprobante y reposiciones del fondo desde caja general. */
class CajaChicaController extends Controller
{
    public function __construct(private readonly CajaChicaService $cajaChica) {}

    public function index(Request $request)
    {
        $desde = $request->date('desde') ?? today();
        $hasta = $request->date('hasta') ?? $desde->copy();
        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }
        $resumen = $this->cajaChica->resumen($desde, $hasta, $request->concepto);

        if (in_array($request->formato, ['pdf', 'xlsx'], true)) {
            return $this->reporte($desde, $hasta, $resumen)->descargar($request->formato, 'caja-chica-'.$desde->toDateString());
        }

        $conceptos = collect(config('erp.caja_chica.conceptos'))->mapWithKeys(fn ($c) => [$c => $c]);

        return $this->tableOrPage($request, 'caja.chica.index', 'caja.chica._table', compact('resumen', 'desde', 'hasta', 'conceptos'));
    }

    public function create(Request $request): View
    {
        $reposicion = $request->tipo === CajaChicaMovimiento::REPOSICION;

        return $this->form(new CajaChicaMovimiento([
            'fecha' => today(),
            'tipo' => $reposicion ? CajaChicaMovimiento::REPOSICION : CajaChicaMovimiento::GASTO,
            'concepto' => $reposicion ? 'Reposición de fondo' : null,
        ]));
    }

    public function store(CajaChicaRequest $request): JsonResponse
    {
        $m = $this->cajaChica->guardar(null, $request->validated());

        return $this->ok($m->esReposicion() ? 'Reposición registrada; se descontó de la caja general.' : 'Gasto de caja chica registrado.', ['reloadPage' => true]);
    }

    public function show(CajaChicaMovimiento $movimiento): View
    {
        return view('caja.chica.show', ['m' => $movimiento->load(['vehiculo', 'chofer', 'user'])]);
    }

    public function edit(CajaChicaMovimiento $movimiento): View
    {
        return $this->form($movimiento);
    }

    public function update(CajaChicaRequest $request, CajaChicaMovimiento $movimiento): JsonResponse
    {
        $this->cajaChica->guardar($movimiento, $request->validated());

        return $this->ok('Movimiento de caja chica actualizado.', ['reloadPage' => true]);
    }

    public function destroy(CajaChicaMovimiento $movimiento): JsonResponse
    {
        $this->cajaChica->eliminar($movimiento);

        return $this->ok('Movimiento de caja chica eliminado.', ['reloadPage' => true]);
    }

    private function form(CajaChicaMovimiento $movimiento): View
    {
        return view('caja.chica.form', [
            'm' => $movimiento,
            'conceptos' => collect(config('erp.caja_chica.conceptos'))->mapWithKeys(fn ($c) => [$c => $c]),
            'vehiculos' => Vehiculo::orderBy('placa')->pluck('placa', 'id'),
            'choferes' => Chofer::activos()->get()->mapWithKeys(fn ($c) => [$c->id => $c->nombre_completo ? "{$c->alias} · {$c->nombre_completo}" : $c->alias]),
        ]);
    }

    /** Formato del «Reporte de movimientos caja chica». */
    private function reporte($desde, $hasta, array $r): Reporte
    {
        $periodo = $desde->equalTo($hasta) ? $desde->format('d-m-Y') : $desde->format('d-m-Y').' al '.$hasta->format('d-m-Y');
        $filas = [['', '', 'SALDO INICIAL', '', '', $r['saldo_inicial'], '', '', '', '', '', '']];
        foreach ($r['filas'] as $i => $f) {
            $m = $f['movimiento'];
            $filas[] = [
                $i + 1, $m->fecha->format('d/m/y'), $m->concepto, $m->descripcion,
                $m->esReposicion() ? $m->monto : -$m->monto, $f['saldo'],
                $m->comprobante, $m->proveedor, $m->ruc, $m->vehiculo?->placa, $m->chofer?->nombre_completo ?: $m->chofer?->alias, $m->observacion,
            ];
        }

        return (new Reporte('Reporte de movimientos caja chica', null, true))
            ->datos(['Fecha' => $periodo, 'Sucursal' => config('erp.caja_chica.sucursal'), 'Responsable' => config('erp.caja_chica.responsable')])
            ->tabla(null, [
                'Ítem' => 'texto', 'Fecha' => 'texto', 'Concepto' => 'texto', 'Descripción' => 'texto', 'Monto' => 'decimal', 'Saldo caja' => 'decimal',
                'N° comprobante' => 'texto', 'Proveedor' => 'texto', 'RUC' => 'texto', 'Vehículo' => 'texto', 'Conductor' => 'texto', 'Observación' => 'texto',
            ], $filas)
            ->tabla('Resumen', ['Concepto' => 'texto', 'Importe' => 'decimal'], [
                ['Saldo inicial', $r['saldo_inicial']],
                ['(+) Reposiciones', $r['reposiciones']],
                ['(−) Total gasto', $r['gastos']],
            ], ['SALDO FINAL CAJA', $r['saldo_final']]);
    }
}
