<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehiculoRequest;
use App\Models\Empresa;
use App\Models\ParteFila;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehiculoController extends Controller
{
    public function index(Request $request)
    {
        $vehiculos = Vehiculo::with(['empresa', 'choferes', 'documentos'])
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('placa', 'like', "%$t%")->orWhere('marca', 'like', "%$t%")->orWhere('modelo', 'like', "%$t%")))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->orderBy('placa')->paginate(25)->withQueryString();

        $resumen = $this->resumen($request, function () {
            $todos = Vehiculo::with('documentos')->get();
            $estados = $todos->flatMap(fn (Vehiculo $v) => collect($v->estadoDocumentos())->pluck('estado'));

            return [
                'total' => $todos->count(),
                'operativos' => $todos->where('estado', 'operativo')->count(),
                'vencidos' => $estados->filter(fn ($e) => $e === 'vencido')->count(),
                'porVencer' => $estados->filter(fn ($e) => $e === 'por_vencer')->count(),
                'sinRegistro' => $estados->filter(fn ($e) => $e === 'sin_registro')->count(),
                'vigentes' => $estados->filter(fn ($e) => in_array($e, ['vigente', 'sin_fecha'], true))->count(),
            ];
        });

        return $this->tableOrPage($request, 'flota.vehiculos.index', 'flota.vehiculos._table', compact('vehiculos', 'resumen'));
    }

    public function create(): View
    {
        return $this->form(new Vehiculo(['estado' => 'operativo', 'tipo' => 'camion']));
    }

    public function store(VehiculoRequest $request): JsonResponse
    {
        $vehiculo = Vehiculo::create($request->validated());

        return $this->ok("Vehículo {$vehiculo->placa} registrado.");
    }

    public function show(Vehiculo $vehiculo): View
    {
        $vehiculo->load(['empresa', 'choferes', 'documentos', 'mantenimientos']);
        $movimientos = ParteFila::with('parte')->where('vehiculo_id', $vehiculo->id)->latest('id')->limit(15)->get();

        return view('flota.vehiculos.show', compact('vehiculo', 'movimientos'));
    }

    public function edit(Vehiculo $vehiculo): View
    {
        return $this->form($vehiculo);
    }

    public function update(VehiculoRequest $request, Vehiculo $vehiculo): JsonResponse
    {
        $vehiculo->update($request->validated());

        return $this->ok("Vehículo {$vehiculo->placa} actualizado.");
    }

    public function destroy(Vehiculo $vehiculo): JsonResponse
    {
        $vehiculo->delete();

        return $this->ok("Vehículo {$vehiculo->placa} eliminado.");
    }

    private function form(Vehiculo $vehiculo): View
    {
        return view('flota.vehiculos.form', ['vehiculo' => $vehiculo, 'empresas' => Empresa::activas()->pluck('nombre', 'id')]);
    }
}
