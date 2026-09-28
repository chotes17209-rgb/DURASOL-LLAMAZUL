<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\CuentaPorCobrar;
use App\Models\LiquidacionItem;
use App\Models\Producto;
use App\Services\PrecioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function __construct(private readonly PrecioService $precios) {}

    public function index(Request $request)
    {
        $clientes = Cliente::with('chofer')
            ->buscar($request->q)
            ->when($request->chofer_id, fn ($q, $c) => $q->where('chofer_id', $c))
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withSum(['cuentasPorCobrar as deuda' => fn ($q) => $q->pendientes()], 'saldo')
            ->orderBy('codigo')->paginate(25)->withQueryString();

        $productos = Producto::where('tipo', Producto::TIPO_GAS)->activos()->get();
        $vigentes = $this->precios->preciosVentaVigentes($clientes->pluck('id')->all());
        $choferes = Chofer::vendedores()->pluck('alias', 'id');

        return $this->tableOrPage($request, 'ventas.clientes.index', 'ventas.clientes._table', compact('clientes', 'productos', 'vigentes', 'choferes'));
    }

    /** JSON para selects con búsqueda (Tom Select). */
    public function buscar(Request $request): JsonResponse
    {
        $clientes = Cliente::activos()->buscar($request->q)
            ->when($request->chofer_id, fn ($q, $c) => $q->orderByRaw('CASE WHEN chofer_id = ? THEN 0 ELSE 1 END', [$c]))
            ->with('chofer')
            ->limit(30)->get();

        return response()->json(['results' => $clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'text' => "{$c->codigo} · {$c->nombreMostrar()}".($c->chofer ? " — {$c->chofer->alias}" : ''),
        ])]);
    }

    public function create(): View
    {
        $siguiente = (int) Cliente::withTrashed()->max('codigo') + 1;

        return $this->form(new Cliente(['codigo' => $siguiente, 'activo' => true, 'tipo' => 'local']));
    }

    public function store(ClienteRequest $request): JsonResponse
    {
        $cliente = DB::transaction(function () use ($request) {
            $data = $request->safe()->except('precios');
            $data['codigo'] ??= (int) Cliente::withTrashed()->max('codigo') + 1;
            $cliente = Cliente::create($data);
            $this->precios->guardarPreciosVenta($cliente, $request->input('precios', []), today(), 'Precio inicial');

            return $cliente;
        });

        return $this->ok("Cliente {$cliente->codigo} · {$cliente->nombre} registrado.");
    }

    public function show(Cliente $cliente): View
    {
        $cliente->load('chofer');
        $productos = Producto::activos()->get()->keyBy('id');
        $vigentes = $this->precios->preciosVentaVigentes([$cliente->id])[$cliente->id] ?? [];
        $historialPrecios = $this->precios->historialCliente($cliente);
        $compras = LiquidacionItem::with(['liquidacion.chofer', 'producto', 'empresa'])
            ->where('cliente_id', $cliente->id)->latest('id')->limit(30)->get();
        $cuentas = CuentaPorCobrar::where('cliente_id', $cliente->id)->latest('fecha')->limit(30)->get();
        $resumen = LiquidacionItem::where('cliente_id', $cliente->id)
            ->selectRaw('producto_id, SUM(cantidad) as cantidad, SUM(total) as total')->groupBy('producto_id')->get();

        return view('ventas.clientes.show', compact('cliente', 'productos', 'vigentes', 'historialPrecios', 'compras', 'cuentas', 'resumen'));
    }

    public function edit(Cliente $cliente): View
    {
        return $this->form($cliente);
    }

    public function update(ClienteRequest $request, Cliente $cliente): JsonResponse
    {
        DB::transaction(function () use ($request, $cliente) {
            $data = $request->safe()->except('precios');
            $data['codigo'] ??= $cliente->codigo;
            $cliente->update($data);
            $this->precios->guardarPreciosVenta($cliente, $request->input('precios', []), today());
        });

        return $this->ok("Cliente {$cliente->nombre} actualizado.");
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        abort_if($cliente->liquidacionItems()->exists(), 422, 'El cliente tiene ventas registradas. Desactívalo en lugar de eliminarlo.');
        $cliente->delete();

        return $this->ok('Cliente eliminado.');
    }

    private function form(Cliente $cliente): View
    {
        return view('ventas.clientes.form', [
            'cliente' => $cliente,
            'choferes' => Chofer::vendedores()->pluck('alias', 'id'),
            'productos' => Producto::activos()->get(),
            'vigentes' => $cliente->exists ? ($this->precios->preciosVentaVigentes([$cliente->id])[$cliente->id] ?? []) : [],
        ]);
    }
}
