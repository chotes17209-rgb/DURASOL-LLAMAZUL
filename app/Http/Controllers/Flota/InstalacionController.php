<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstalacionRequest;
use App\Models\Chofer;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\ParteFila;
use App\Models\Producto;
use App\Models\Vehiculo;
use App\Services\PrecioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstalacionController extends Controller
{
    public function index(Request $request, PrecioService $precios)
    {
        $instalaciones = Instalacion::with(['empresa', 'chofer', 'vehiculo'])
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('codigo', 'like', "%$t%")->orWhere('nombre', 'like', "%$t%")))
            ->when($request->empresa_id, fn ($q, $e) => $q->where('empresa_id', $e))
            ->orderBy('empresa_id')->orderBy('codigo')->paginate(25)->withQueryString();

        $vigentes = $precios->preciosCompraVigentes();
        $productos = Producto::dePlanta()->get();
        $empresas = Empresa::activas()->pluck('nombre', 'id');

        return $this->tableOrPage($request, 'flota.instalaciones.index', 'flota.instalaciones._table', compact('instalaciones', 'vigentes', 'productos', 'empresas'));
    }

    public function create(): View
    {
        return $this->form(new Instalacion(['activo' => true]));
    }

    public function store(InstalacionRequest $request): JsonResponse
    {
        $instalacion = Instalacion::create($request->validated());

        return $this->ok("Instalación {$instalacion->codigo} registrada. Recuerda cargar sus precios de compra.");
    }

    public function show(Instalacion $instalacion, PrecioService $precios): View
    {
        $instalacion->load(['empresa', 'chofer', 'vehiculo']);
        $historialPrecios = $instalacion->preciosCompra()->with(['producto', 'user'])->orderByDesc('vigente_desde')->orderByDesc('id')->get();
        $movimientos = ParteFila::with('parte')->where('instalacion_id', $instalacion->id)->latest('id')->limit(15)->get();
        $vigentes = $precios->preciosCompraVigentes()[$instalacion->id] ?? [];

        return view('flota.instalaciones.show', compact('instalacion', 'historialPrecios', 'movimientos', 'vigentes'));
    }

    public function edit(Instalacion $instalacion): View
    {
        return $this->form($instalacion);
    }

    public function update(InstalacionRequest $request, Instalacion $instalacion): JsonResponse
    {
        $instalacion->update($request->validated());

        return $this->ok("Instalación {$instalacion->codigo} actualizada.");
    }

    public function destroy(Instalacion $instalacion): JsonResponse
    {
        abort_if(ParteFila::where('instalacion_id', $instalacion->id)->exists(), 422, 'La instalación tiene cargas registradas en los partes. Desactívala en lugar de eliminarla.');
        $instalacion->delete();

        return $this->ok('Instalación eliminada.');
    }

    private function form(Instalacion $instalacion): View
    {
        return view('flota.instalaciones.form', [
            'instalacion' => $instalacion,
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
            'choferes' => Chofer::activos()->pluck('alias', 'id'),
            'vehiculos' => Vehiculo::operativos()->pluck('placa', 'id'),
        ]);
    }
}
