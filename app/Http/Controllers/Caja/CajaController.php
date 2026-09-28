<?php

namespace App\Http\Controllers\Caja;

use App\Enums\CategoriaCaja;
use App\Enums\EstadoLiquidacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\CajaMovimientoRequest;
use App\Models\CajaMovimiento;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Services\CajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Caja general: ingresos (liquidaciones, cobranzas) y egresos (depósitos, gastos, caja chica).
 */
class CajaController extends Controller
{
    public function __construct(private readonly CajaService $caja) {}

    public function index(Request $request)
    {
        $desde = $request->date('desde') ?? today();
        $hasta = $request->date('hasta') ?? $desde->copy();

        $movimientos = CajaMovimiento::with(['empresa', 'user', 'origen'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->categoria, fn ($q, $c) => $q->where('categoria', $c))
            ->orderBy('fecha')->orderBy('id')->paginate(50)->withQueryString();

        $resumen = $this->caja->resumen($desde, $hasta);
        $porCerrar = Liquidacion::with('chofer')->where('estado', EstadoLiquidacion::Borrador)->orderBy('fecha_venta')->get();

        return $this->tableOrPage($request, 'caja.index', 'caja._table', compact('movimientos', 'resumen', 'desde', 'hasta', 'porCerrar'));
    }

    public function create(Request $request): View
    {
        return $this->form(new CajaMovimiento(['fecha' => $this->fechaSugerida($request), 'tipo' => $request->tipo === 'ingreso' ? CajaMovimiento::INGRESO : CajaMovimiento::EGRESO, 'categoria' => CategoriaCaja::Gasto]));
    }

    public function store(CajaMovimientoRequest $request): JsonResponse
    {
        CajaMovimiento::create($request->validated() + ['user_id' => auth()->id()]);

        return $this->ok('Movimiento de caja registrado.', ['reloadPage' => true]);
    }

    public function show(CajaMovimiento $movimiento): View
    {
        $movimiento->load(['empresa', 'user', 'origen']);

        return view('caja.show', compact('movimiento'));
    }

    public function edit(CajaMovimiento $movimiento): View
    {
        abort_if($movimiento->esAutomatico(), 422, 'Este movimiento lo generó otro documento; modifícalo desde su origen.');

        return $this->form($movimiento);
    }

    public function update(CajaMovimientoRequest $request, CajaMovimiento $movimiento): JsonResponse
    {
        abort_if($movimiento->esAutomatico(), 422, 'Este movimiento lo generó otro documento; modifícalo desde su origen.');
        $movimiento->update($request->validated());

        return $this->ok('Movimiento de caja actualizado.', ['reloadPage' => true]);
    }

    public function destroy(CajaMovimiento $movimiento): JsonResponse
    {
        abort_if($movimiento->esAutomatico(), 422, 'Este movimiento lo generó otro documento; anúlalo desde su origen.');
        $movimiento->delete();

        return $this->ok('Movimiento de caja eliminado.', ['reloadPage' => true]);
    }

    private function fechaSugerida(Request $request): Carbon
    {
        $fecha = $request->date('fecha');

        return $fecha && $fecha->lte(today()) ? $fecha : today();
    }

    private function form(CajaMovimiento $movimiento): View
    {
        $categorias = collect([CategoriaCaja::Gasto, CategoriaCaja::Planilla, CategoriaCaja::Otro])
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()]);

        return view('caja.form', ['movimiento' => $movimiento, 'categorias' => $categorias, 'empresas' => Empresa::activas()->pluck('nombre', 'id')]);
    }
}
