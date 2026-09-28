<?php

namespace App\Http\Controllers\Ventas;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoLiquidacion;
use App\Enums\MetodoPago;
use App\Enums\TipoChofer;
use App\Http\Controllers\Controller;
use App\Http\Requests\LiquidacionRequest;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Despacho;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\LiquidacionFise;
use App\Models\Producto;
use App\Models\Vehiculo;
use App\Services\CuentaService;
use App\Services\LiquidacionService;
use App\Services\PrecioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiquidacionController extends Controller
{
    public function __construct(
        private readonly LiquidacionService $service,
        private readonly PrecioService $precios,
        private readonly CuentaService $cuentas,
    ) {}

    public function index(Request $request)
    {
        $liquidaciones = Liquidacion::with(['chofer', 'vehiculo'])->withSum('items as balones', 'cantidad')
            ->when($request->q, fn ($q, $t) => $q->where('codigo', 'like', "%$t%"))
            ->when($request->chofer_id, fn ($q, $c) => $q->where('chofer_id', $c))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->desde, fn ($q, $d) => $q->where('fecha_venta', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->where('fecha_venta', '<=', $h))
            ->orderByDesc('fecha_venta')->orderByDesc('id')->paginate(25)->withQueryString();

        $borradores = Liquidacion::where('estado', EstadoLiquidacion::Borrador)->count();
        $choferes = Chofer::vendedores()->pluck('alias', 'id');

        return $this->tableOrPage($request, 'ventas.liquidaciones.index', 'ventas.liquidaciones._table', compact('liquidaciones', 'borradores', 'choferes'));
    }

    public function create(Request $request): View
    {
        $liquidacion = new Liquidacion([
            'fecha_venta' => today()->subDays(config('erp.dias_liquidacion')),
            'fecha_liquidacion' => today(),
            'chofer_id' => $request->chofer_id,
            'tipo' => TipoChofer::Local,
            'estado' => EstadoLiquidacion::Borrador,
        ]);

        return $this->editor($liquidacion);
    }

    public function store(LiquidacionRequest $request): JsonResponse
    {
        $liquidacion = $this->service->guardar(null, $request->validated());

        return $this->respuestaGuardado($liquidacion, "Liquidación {$liquidacion->codigo} guardada como borrador.");
    }

    public function show(Liquidacion $liquidacion): View
    {
        $liquidacion->load(['chofer', 'vehiculo', 'user', 'cerradaPor', 'items.cliente', 'items.producto', 'items.empresa',
            'fises.cliente', 'gastos', 'cobranzas.cliente', 'cuentasPorCobrar']);
        $porProducto = $liquidacion->items->groupBy('producto_id')->map(fn ($g) => [
            'codigo' => $g->first()->producto?->codigo, 'cantidad' => $g->sum('cantidad'), 'total' => $g->sum('total'), 'vacios' => $g->sum('vacios_devueltos'),
        ]);

        return view('ventas.liquidaciones.show', compact('liquidacion', 'porProducto'));
    }

    public function edit(Liquidacion $liquidacion): View
    {
        return $this->editor($liquidacion);
    }

    public function update(LiquidacionRequest $request, Liquidacion $liquidacion): JsonResponse
    {
        $this->service->guardar($liquidacion, $request->validated());

        return $this->respuestaGuardado($liquidacion, "Liquidación {$liquidacion->codigo} actualizada.");
    }

    public function cerrarForm(Liquidacion $liquidacion): View
    {
        abort_unless($liquidacion->esEditable(), 422, 'La liquidación no está en borrador.');
        $liquidacion->load(['chofer', 'items', 'cobranzas.cliente']);

        return view('ventas.liquidaciones.cerrar', compact('liquidacion'));
    }

    public function cerrar(Request $request, Liquidacion $liquidacion): JsonResponse
    {
        $data = $request->validate(['efectivo_entregado' => ['required', 'numeric', 'min:0']], [], ['efectivo_entregado' => 'efectivo entregado']);
        $this->service->cerrar($liquidacion, (float) $data['efectivo_entregado']);
        $liquidacion->refresh();
        $mensaje = "Liquidación {$liquidacion->codigo} cerrada. Ingresaron ".soles($liquidacion->efectivo_entregado).' a caja.';
        if (abs((float) $liquidacion->diferencia) >= 0.01) {
            $mensaje .= ' Diferencia: '.soles($liquidacion->diferencia).'.';
        }

        return $this->ok($mensaje, ['reloadPage' => true]);
    }

    public function reabrir(Liquidacion $liquidacion): JsonResponse
    {
        $this->service->reabrir($liquidacion);

        return $this->ok("Liquidación {$liquidacion->codigo} reabierta. Sus créditos, cobranzas y el ingreso a caja se revirtieron.", ['reloadPage' => true]);
    }

    public function anular(Request $request, Liquidacion $liquidacion): JsonResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'max:200']]);
        $this->service->anular($liquidacion, $request->motivo);

        return $this->ok("Liquidación {$liquidacion->codigo} anulada.", ['reloadPage' => true]);
    }

    public function destroy(Liquidacion $liquidacion): JsonResponse
    {
        abort_if($liquidacion->estado === EstadoLiquidacion::Cerrada, 422, 'No se puede eliminar una liquidación cerrada. Reábrela o anúlala.');
        $liquidacion->delete();

        return $this->ok("Liquidación {$liquidacion->codigo} eliminada.");
    }

    /** Precios vigentes y deuda de un cliente para el editor. */
    public function datosCliente(Request $request): JsonResponse
    {
        $cliente = Cliente::with('chofer')->findOrFail($request->integer('cliente_id'));
        $precios = $this->precios->preciosVentaVigentes([$cliente->id], $request->fecha ?: today())[$cliente->id] ?? [];

        return response()->json([
            'id' => $cliente->id,
            'nombre' => $cliente->nombreMostrar(),
            'codigo' => $cliente->codigo,
            'chofer' => $cliente->chofer?->alias,
            'precios' => (object) $precios,
            'deuda' => $this->cuentas->deudaCliente($cliente->id),
        ]);
    }

    /** Balones vendidos según logística (despachos retornados) para cuadrar. */
    public function cuadre(Request $request): JsonResponse
    {
        $despachos = Despacho::with('detalles.producto')
            ->where('chofer_id', $request->integer('chofer_id'))
            ->where('fecha', $request->date('fecha')?->toDateString())
            ->where('estado', EstadoDespacho::Retornado)->get();

        $vendidos = [];
        foreach ($despachos as $d) {
            foreach ($d->detalles as $det) {
                $codigo = $det->producto->codigo;
                $vendidos[$codigo] = ($vendidos[$codigo] ?? 0) + $det->vendidos();
            }
        }

        return response()->json((object) $vendidos);
    }

    private function respuestaGuardado(Liquidacion $liquidacion, string $mensaje): JsonResponse
    {
        return $this->ok($mensaje, [
            'redirect' => route('liquidaciones.edit', $liquidacion),
            'cerrarUrl' => route('liquidaciones.cerrar', $liquidacion),
        ]);
    }

    private function editor(Liquidacion $liquidacion): View
    {
        $liquidacion->loadMissing(['items.cliente', 'fises', 'cobranzas.cliente', 'gastos']);
        $choferes = Chofer::vendedores()->get();
        $productos = Producto::activos()->get();

        // Datos de los clientes ya cargados (nombre, precios y deuda) para no pedirlos otra vez.
        $clienteIds = $liquidacion->items->pluck('cliente_id')->merge($liquidacion->cobranzas->pluck('cliente_id'))->unique()->values()->all();
        $vigentes = $clienteIds ? $this->precios->preciosVentaVigentes($clienteIds, $liquidacion->fecha_venta) : [];
        $clientes = [];
        foreach (Cliente::withTrashed()->whereIn('id', $clienteIds)->get() as $c) {
            $clientes[$c->id] = ['id' => $c->id, 'nombre' => $c->nombreMostrar(), 'codigo' => $c->codigo, 'precios' => (object) ($vigentes[$c->id] ?? []), 'deuda' => $this->cuentas->deudaCliente($c->id)];
        }

        $fises = [];
        foreach ($liquidacion->fises as $f) {
            $fises[$f->cliente_id ?? 'sin'][(int) $f->valor] = $f->cantidad;
        }

        $config = [
            'metodo' => $liquidacion->exists ? 'PUT' : 'POST',
            'editable' => $liquidacion->esEditable(),
            'diasLiquidacion' => config('erp.dias_liquidacion'),
            'urls' => [
                'guardar' => $liquidacion->exists ? route('liquidaciones.update', $liquidacion) : route('liquidaciones.store'),
                'buscarClientes' => route('buscar.clientes'),
                'datosCliente' => route('liquidaciones.datos-cliente'),
                'cuadre' => route('liquidaciones.cuadre'),
            ],
            'cabecera' => [
                'fecha_venta' => $liquidacion->fecha_venta?->format('Y-m-d'),
                'fecha_liquidacion' => $liquidacion->fecha_liquidacion?->format('Y-m-d'),
                'chofer_id' => $liquidacion->chofer_id ? (string) $liquidacion->chofer_id : '',
                'vehiculo_id' => $liquidacion->vehiculo_id ? (string) $liquidacion->vehiculo_id : '',
                'tipo' => $liquidacion->tipo?->value ?? 'local',
                'efectivo_entregado' => $liquidacion->efectivo_entregado ?? '',
                'observaciones' => $liquidacion->observaciones ?? '',
            ],
            'items' => $liquidacion->items->map(fn ($i) => [
                'uid' => (string) $i->id, 'cliente_id' => $i->cliente_id, 'producto_id' => $i->producto_id, 'empresa_id' => $i->empresa_id,
                'cantidad' => $i->cantidad, 'precio' => (float) $i->precio, 'vacios_devueltos' => $i->vacios_devueltos ?: '',
                'metodo_pago' => $i->metodo_pago->value, 'es_credito' => (float) $i->monto_credito > 0, 'monto_credito' => (float) $i->monto_credito ?: '',
                'numero_operacion' => $i->numero_operacion ?? '', 'observacion' => $i->observacion ?? '',
            ])->values(),
            'fises' => (object) $fises,
            'cobranzas' => $liquidacion->cobranzas->map(fn ($c) => ['uid' => (string) $c->id, 'cliente_id' => $c->cliente_id, 'monto' => (float) $c->monto, 'metodo_pago' => $c->metodo_pago->value, 'numero_operacion' => $c->numero_operacion ?? ''])->values(),
            'gastos' => $liquidacion->gastos->map(fn ($g) => ['uid' => (string) $g->id, 'concepto' => $g->concepto, 'monto' => (float) $g->monto, 'comprobante' => $g->comprobante ?? ''])->values(),
            'clientes' => (object) $clientes,
            'productos' => $productos->map(fn ($p) => ['id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre])->values(),
            'empresas' => Empresa::activas()->get(['id', 'nombre'])->values(),
            'choferes' => $choferes->map(fn ($c) => ['id' => $c->id, 'alias' => $c->alias, 'tipo' => $c->tipo->value, 'vehiculo_id' => $c->vehiculo_id])->values(),
            'metodos' => MetodoPago::options(),
            'valoresFise' => LiquidacionFise::VALORES,
        ];

        return view('ventas.liquidaciones.editor', [
            'liquidacion' => $liquidacion,
            'config' => $config,
            'choferes' => $choferes,
            'vehiculos' => Vehiculo::operativos()->pluck('placa', 'id'),
        ]);
    }
}
