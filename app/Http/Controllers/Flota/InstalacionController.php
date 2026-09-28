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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InstalacionController extends Controller
{
    public function index(Request $request, PrecioService $precios)
    {
        return $this->tableOrPage($request, 'flota.instalaciones.index', 'flota.instalaciones._table', $precios->cuadroInstalaciones($request));
    }

    public function create(): View
    {
        return $this->form(new Instalacion(['activo' => true]));
    }

    public function store(InstalacionRequest $request, PrecioService $precios): JsonResponse
    {
        $instalacion = DB::transaction(function () use ($request, $precios) {
            $instalacion = Instalacion::create($request->safe()->except(['precios', 'vigente_desde']));
            $precios->guardarPreciosCompra($instalacion, $request->input('precios', []), $request->date('vigente_desde') ?? today(), 'Precio inicial');

            return $instalacion;
        });

        return $this->ok("Instalación {$instalacion->codigo} registrada.", ['reloadPage' => true]);
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

    public function update(InstalacionRequest $request, Instalacion $instalacion, PrecioService $precios): JsonResponse
    {
        $cambios = DB::transaction(function () use ($request, $instalacion, $precios) {
            $instalacion->update($request->safe()->except(['precios', 'vigente_desde']));

            return $precios->guardarPreciosCompra($instalacion, $request->input('precios', []), $request->date('vigente_desde') ?? today(), 'Cambio de precio');
        });

        return $this->ok("Instalación {$instalacion->codigo} actualizada".($cambios ? " con {$cambios} precio(s) nuevo(s) por validar." : '.'), ['reloadPage' => true]);
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
            'productos' => Producto::dePlanta()->get(),
            'vigentes' => app(PrecioService::class)->preciosCompraVigentes()[$instalacion->id] ?? [],
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
            'choferes' => Chofer::activos()->pluck('alias', 'id'),
            'vehiculos' => Vehiculo::operativos()->pluck('placa', 'id'),
        ]);
    }
}
