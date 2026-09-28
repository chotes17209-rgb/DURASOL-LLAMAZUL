<?php

namespace App\Http\Controllers\Precios;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreciosCompraRequest;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\PrecioCompra;
use App\Models\Producto;
use App\Services\PrecioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Precios de compra en planta, divididos por empresa e instalación.
 */
class PrecioCompraController extends Controller
{
    public function __construct(private readonly PrecioService $precios) {}

    public function index(Request $request)
    {
        return $this->tableOrPage($request, 'precios.compra.index', 'precios.compra._tabla', $this->precios->cuadroInstalaciones($request));
    }

    public function create(Request $request): View
    {
        $instalacion = Instalacion::with('empresa')->findOrFail($request->instalacion_id);

        return view('precios.compra.form', [
            'instalacion' => $instalacion,
            'productos' => Producto::dePlanta()->get(),
            'vigentes' => $this->precios->preciosCompraVigentes()[$instalacion->id] ?? [],
        ]);
    }

    public function store(PreciosCompraRequest $request): JsonResponse
    {
        $instalacion = Instalacion::findOrFail($request->instalacion_id);
        $cambios = $this->precios->guardarPreciosCompra($instalacion, $request->input('precios', []), $request->date('vigente_desde'), $request->motivo);

        return $cambios
            ? $this->ok("Se registraron {$cambios} precio(s) de compra para {$instalacion->codigo}.", ['reloadPage' => true])
            : $this->ok('No hubo cambios de precio.', ['type' => 'info']);
    }

    public function historial(Request $request)
    {
        $registros = PrecioCompra::with(['empresa', 'instalacion', 'producto', 'user', 'validadoPor'])
            ->when($request->empresa_id, fn ($q, $e) => $q->where('empresa_id', $e))
            ->when($request->instalacion_id, fn ($q, $i) => $q->where('instalacion_id', $i))
            ->when($request->producto_id, fn ($q, $p) => $q->where('producto_id', $p))
            ->orderByDesc('vigente_desde')->orderByDesc('id')->paginate(40)->withQueryString();

        $empresas = Empresa::activas()->pluck('nombre', 'id');
        $instalaciones = Instalacion::orderBy('codigo')->get()->mapWithKeys(fn ($i) => [$i->id => $i->nombreMostrar()]);
        $productos = Producto::activos()->pluck('codigo', 'id');

        return $this->tableOrPage($request, 'precios.compra.historial', 'precios.compra._historial', compact('registros', 'empresas', 'instalaciones', 'productos'));
    }

    /** Marca como validados los precios vigentes de la instalación (ya figuran en las facturas). */
    public function validar(Instalacion $instalacion): JsonResponse
    {
        $ids = collect($this->precios->preciosCompraVigentes()[$instalacion->id] ?? [])->where('validado', false)->pluck('id');
        PrecioCompra::whereIn('id', $ids)->get()->each->update(['validado' => true, 'validado_por' => auth()->id(), 'validado_at' => now()]);

        return $this->ok("Precios de {$instalacion->codigo} validados.", ['reloadPage' => true]);
    }

    public function destroy(PrecioCompra $precio): JsonResponse
    {
        $precio->delete();

        return $this->ok('Registro de precio de compra eliminado.');
    }
}
