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
use Illuminate\Validation\ValidationException;
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
        $hayApertura = CajaChicaMovimiento::where('tipo', CajaChicaMovimiento::APERTURA)->exists();

        return $this->tableOrPage($request, 'caja.chica.index', 'caja.chica._table', compact('resumen', 'desde', 'hasta', 'conceptos', 'hayApertura'));
    }

    public function create(Request $request): View
    {
        $tipo = in_array($request->tipo, [CajaChicaMovimiento::APERTURA, CajaChicaMovimiento::REPOSICION], true) ? $request->tipo : CajaChicaMovimiento::GASTO;

        return $this->form(new CajaChicaMovimiento([
            'fecha' => $request->date('fecha')?->lte(today()) ? $request->date('fecha') : today(),
            'tipo' => $tipo,
            'concepto' => match ($tipo) {
                CajaChicaMovimiento::APERTURA => 'Saldo inicial', CajaChicaMovimiento::REPOSICION => 'Reposición de fondo', default => null
            },
        ]));
    }

    public function store(CajaChicaRequest $request): JsonResponse
    {
        $this->validarApertura($request);
        $m = $this->cajaChica->guardar(null, $request->validated());

        return $this->ok(match ($m->tipo) {
            CajaChicaMovimiento::APERTURA => 'Saldo inicial registrado. Desde el día siguiente el saldo se arrastra automáticamente.',
            CajaChicaMovimiento::REPOSICION => 'Reposición registrada; se descontó de la caja general.',
            default => 'Gasto de caja chica registrado.',
        }, ['reloadPage' => true]);
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
        $this->validarApertura($request, $movimiento);
        $this->cajaChica->guardar($movimiento, $request->validated());

        return $this->ok('Movimiento de caja chica actualizado.', ['reloadPage' => true]);
    }

    public function destroy(CajaChicaMovimiento $movimiento): JsonResponse
    {
        $this->cajaChica->eliminar($movimiento);

        return $this->ok('Movimiento de caja chica eliminado.', ['reloadPage' => true]);
    }

    /** Solo existe un saldo inicial, y ningún movimiento puede quedar antes de él. */
    private function validarApertura(Request $request, ?CajaChicaMovimiento $actual = null): void
    {
        if ($request->tipo !== CajaChicaMovimiento::APERTURA) {
            $apertura = CajaChicaMovimiento::where('tipo', CajaChicaMovimiento::APERTURA)->first();
            if ($apertura && $request->date('fecha')->lt($apertura->fecha)) {
                throw ValidationException::withMessages(['fecha' => 'La fecha es anterior al saldo inicial de la caja chica ('.$apertura->fecha->format('d/m/Y').').']);
            }

            return;
        }
        $otra = CajaChicaMovimiento::where('tipo', CajaChicaMovimiento::APERTURA)->when($actual, fn ($q) => $q->whereKeyNot($actual->id))->exists();
        if ($otra) {
            throw ValidationException::withMessages(['monto' => 'La caja chica ya tiene saldo inicial; desde entonces el saldo se toma del día anterior.']);
        }
        $anterior = CajaChicaMovimiento::where('tipo', '!=', CajaChicaMovimiento::APERTURA)->where('fecha', '<', $request->date('fecha')->toDateString())->exists();
        if ($anterior) {
            throw ValidationException::withMessages(['fecha' => 'Hay movimientos anteriores a esa fecha; el saldo inicial debe ser la fecha del primer movimiento.']);
        }
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
