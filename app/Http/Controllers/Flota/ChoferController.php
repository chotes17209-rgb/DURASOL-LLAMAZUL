<?php

namespace App\Http\Controllers\Flota;

use App\Enums\TipoChofer;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChoferRequest;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChoferController extends Controller
{
    public function index(Request $request)
    {
        $choferes = Chofer::with('vehiculo')->withCount('clientes')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->whereLike('alias', "%$t%")->orWhereLike('nombre_completo', "%$t%")->orWhereLike('dni', "%$t%")))
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderByDesc('activo')->orderBy('alias')->paginate(25)->withQueryString();

        $resumen = $this->resumen($request, fn () => [
            'activos' => Chofer::where('activo', true)->count(),
            'locales' => Chofer::where('activo', true)->where('tipo', TipoChofer::Local)->count(),
            'ruta' => Chofer::where('activo', true)->where('tipo', TipoChofer::Ruta)->count(),
            'clientes' => Cliente::where('activo', true)->whereNotNull('chofer_id')->count(),
        ]);

        return $this->tableOrPage($request, 'flota.choferes.index', 'flota.choferes._table', compact('choferes', 'resumen'));
    }

    public function create(): View
    {
        return $this->form(new Chofer(['activo' => true, 'tipo' => TipoChofer::Local]));
    }

    public function store(ChoferRequest $request): JsonResponse
    {
        $chofer = Chofer::create($request->validated());

        return $this->ok("Chofer {$chofer->alias} registrado.");
    }

    public function show(Chofer $chofer): View
    {
        $chofer->load(['vehiculo', 'clientes' => fn ($q) => $q->orderBy('nombre')]);
        $liquidaciones = $chofer->liquidaciones()->latest('fecha_venta')->limit(10)->get();
        $movimientos = $chofer->filasParte()->with('parte')->latest('id')->limit(15)->get();

        return view('flota.choferes.show', compact('chofer', 'liquidaciones', 'movimientos'));
    }

    public function edit(Chofer $chofer): View
    {
        return $this->form($chofer);
    }

    public function update(ChoferRequest $request, Chofer $chofer): JsonResponse
    {
        $chofer->update($request->validated());

        return $this->ok("Chofer {$chofer->alias} actualizado.");
    }

    public function destroy(Chofer $chofer): JsonResponse
    {
        abort_if($chofer->liquidaciones()->exists() || $chofer->filasParte()->exists(), 422,
            'El chofer tiene liquidaciones o movimientos de almacén. Desactívalo en lugar de eliminarlo.');
        $chofer->delete();

        return $this->ok('Chofer eliminado.');
    }

    private function form(Chofer $chofer): View
    {
        return view('flota.choferes.form', ['chofer' => $chofer, 'vehiculos' => Vehiculo::operativos()->pluck('placa', 'id')]);
    }
}
