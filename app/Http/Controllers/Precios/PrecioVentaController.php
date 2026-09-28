<?php

namespace App\Http\Controllers\Precios;

use App\Http\Controllers\Controller;
use App\Http\Requests\AjustePrecioRequest;
use App\Http\Requests\PreciosVentaRequest;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\PrecioVenta;
use App\Models\Producto;
use App\Services\PrecioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Precios de venta por cliente. Cada cambio queda en el historial del cliente.
 */
class PrecioVentaController extends Controller
{
    public function __construct(private readonly PrecioService $precios) {}

    public function index(Request $request)
    {
        $clientes = Cliente::with('chofer')->where('activo', true)
            ->buscar($request->q)
            ->when($request->chofer_id, fn ($q, $c) => $q->where('chofer_id', $c))
            ->orderBy('codigo')->paginate(30)->withQueryString();

        $productos = Producto::activos()->get();
        $vigentes = $this->precios->preciosVentaVigentes($clientes->pluck('id')->all());
        $choferes = Chofer::vendedores()->pluck('alias', 'id');

        $resumen = $this->resumen($request, function () {
            $s10 = Producto::where('codigo', 'S10')->value('id');
            $todos = collect($this->precios->preciosVentaVigentes())->map(fn ($p) => $p[$s10] ?? null)->filter();

            return [
                'clientes' => Cliente::where('activo', true)->count(),
                'minimo' => $todos->min(),
                'maximo' => $todos->max(),
                'promedio' => $todos->avg(),
                'ultimo' => PrecioVenta::max('vigente_desde'),
            ];
        });

        return $this->tableOrPage($request, 'precios.venta.index', 'precios.venta._table', compact('clientes', 'productos', 'vigentes', 'choferes', 'resumen'));
    }

    public function show(Cliente $cliente): View
    {
        $historial = $this->precios->historialCliente($cliente);
        $productos = Producto::activos()->get()->keyBy('id');

        return view('precios.venta.show', compact('cliente', 'historial', 'productos'));
    }

    public function edit(Request $request, Cliente $cliente): View
    {
        return view('precios.venta.form', [
            'cliente' => $cliente->load('chofer'),
            'vigenteDesde' => $request->date('vigente_desde') ?? today(),
            'productos' => Producto::activos()->get(),
            'vigentes' => $this->precios->preciosVentaVigentes([$cliente->id])[$cliente->id] ?? [],
        ]);
    }

    public function update(PreciosVentaRequest $request, Cliente $cliente): JsonResponse
    {
        $cambios = $this->precios->guardarPreciosVenta($cliente, $request->input('precios', []), $request->date('vigente_desde'), $request->motivo);

        return $cambios
            ? $this->ok("Se actualizaron {$cambios} precio(s) de {$cliente->nombre}.")
            : $this->ok('No hubo cambios de precio.', ['type' => 'info']);
    }

    public function ajusteForm(): View
    {
        return view('precios.venta.ajuste', [
            'productos' => Producto::activos()->pluck('nombre', 'id'),
            'choferes' => Chofer::vendedores()->pluck('alias', 'id'),
        ]);
    }

    public function ajuste(AjustePrecioRequest $request): JsonResponse
    {
        $actualizados = $this->precios->ajusteMasivoVenta(
            (int) $request->producto_id,
            (float) $request->variacion,
            Carbon::parse($request->vigente_desde),
            $request->motivo,
            $request->chofer_id ? (int) $request->chofer_id : null,
        );

        return $this->ok("Precio actualizado para {$actualizados} cliente(s).");
    }

    /** Todos los cambios de precios de venta (auditoría comercial). */
    public function historial(Request $request)
    {
        $registros = PrecioVenta::with(['cliente', 'producto', 'user'])
            ->when($request->producto_id, fn ($q, $p) => $q->where('producto_id', $p))
            ->when($request->q, fn ($q, $t) => $q->whereHas('cliente', fn ($c) => $c->buscar($t)))
            ->when($request->desde, fn ($q, $d) => $q->where('vigente_desde', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->where('vigente_desde', '<=', $h))
            ->orderByDesc('vigente_desde')->orderByDesc('id')->paginate(40)->withQueryString();
        $productos = Producto::activos()->pluck('codigo', 'id');

        return $this->tableOrPage($request, 'precios.venta.historial', 'precios.venta._historial', compact('registros', 'productos'));
    }

    public function destroy(PrecioVenta $precio): JsonResponse
    {
        $precio->delete();

        return $this->ok('Registro de precio eliminado.', ['reloadModal' => true]);
    }
}
