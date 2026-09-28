<?php

namespace App\Http\Controllers\Ventas;

use App\Enums\EstadoLiquidacion;
use App\Enums\MetodoPago;
use App\Enums\TipoChofer;
use App\Http\Controllers\Controller;
use App\Http\Requests\LiquidacionRequest;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\LiquidacionFise;
use App\Models\Producto;
use App\Models\Vehiculo;
use App\Services\AlmacenService;
use App\Services\CuentaService;
use App\Services\LiquidacionService;
use App\Services\PrecioService;
use App\Support\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiquidacionController extends Controller
{
    public function __construct(
        private readonly LiquidacionService $service,
        private readonly PrecioService $precios,
        private readonly CuentaService $cuentas,
        private readonly AlmacenService $almacen,
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

    public function show(Request $request, Liquidacion $liquidacion)
    {
        $liquidacion->load(['chofer', 'vehiculo', 'user', 'cerradaPor', 'items.cliente', 'items.producto', 'items.empresa',
            'fises.cliente', 'gastos', 'cobranzas.cliente', 'cuentasPorCobrar']);
        if (in_array($request->formato, ['pdf', 'xlsx'], true)) {
            return $this->reporte($liquidacion)->descargar($request->formato, 'liquidacion-'.$liquidacion->codigo);
        }
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
        // Por id (buscador) o por código (se escribe el código en la hoja, como el BUSCARV del Excel).
        $cliente = $request->filled('codigo')
            ? Cliente::with('chofer')->where('codigo', $request->integer('codigo'))->first()
            : Cliente::with('chofer')->find($request->integer('cliente_id'));
        abort_unless($cliente, 404, 'No existe un cliente con ese código.');
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

    /** Balones vendidos según el parte de almacén (salida − retorno de llenos) para cuadrar. */
    public function cuadre(Request $request): JsonResponse
    {
        $fecha = $request->date('fecha');
        if (! $fecha) {
            return response()->json((object) []);
        }
        $fila = $this->almacen->cuadreChoferes($fecha)->first(fn ($c) => $c['chofer']->id === $request->integer('chofer_id'));
        $vendidos = collect($fila['productos'] ?? [])->filter(fn ($p) => $p['salio'] > 0)->map(fn ($p) => $p['vendido']);

        return response()->json((object) $vendidos->all());
    }

    /** Liquidación individual en el formato de la hoja REGISTRO. */
    private function reporte(Liquidacion $l): Reporte
    {
        $l->loadMissing(['items.cliente', 'items.producto', 'items.empresa', 'fises.cliente', 'gastos', 'cobranzas.cliente', 'chofer', 'vehiculo']);
        $reporte = (new Reporte('Liquidación '.$l->codigo, 'Venta del '.$l->fecha_venta->format('d/m/Y').' · liquidada el '.$l->fecha_liquidacion->format('d/m/Y'), true))
            ->datos(['Responsable' => $l->chofer?->alias, 'Placa' => $l->vehiculo?->placa ?? 'LOCAL', 'Estado' => $l->estado->label()]);

        $reporte->tabla('Registro de ventas', [
            'Código' => 'texto', 'Cliente' => 'texto', 'Empresa' => 'texto', 'Pres.' => 'texto', 'Cant.' => 'entero', 'Precio' => 'decimal',
            'Total' => 'decimal', 'Bal. dev.' => 'entero', 'Crédito' => 'decimal', 'Contado' => 'decimal', 'Pago' => 'texto', 'N° op.' => 'texto',
        ], $l->items->map(fn ($i) => [
            $i->cliente?->codigo, $i->cliente?->nombreMostrar(), $i->empresa?->nombre, $i->producto?->codigo, $i->cantidad, $i->precio,
            $i->total, $i->vacios_devueltos, $i->monto_credito, (float) $i->total - (float) $i->monto_credito, $i->metodo_pago->label(), $i->numero_operacion,
        ]), ['TOTAL', '', '', '', $l->items->sum('cantidad'), '', $l->total_venta, $l->items->sum('vacios_devueltos'), $l->total_credito, (float) $l->total_venta - (float) $l->total_credito, '', '']);

        if ($l->cobranzas->isNotEmpty()) {
            $reporte->tabla('Cobranzas', ['Código' => 'texto', 'Cliente' => 'texto', 'Pago' => 'texto', 'Monto' => 'decimal'],
                $l->cobranzas->map(fn ($c) => [$c->cliente?->codigo, $c->cliente?->nombreMostrar(), $c->metodo_pago->label(), $c->monto]), ['TOTAL', '', '', $l->total_cobranzas]);
        }
        if ($l->fises->isNotEmpty()) {
            $reporte->tabla('Vales FISE', ['Cliente' => 'texto', 'Valor' => 'decimal', 'Cantidad' => 'entero', 'Importe' => 'decimal'],
                $l->fises->map(fn ($f) => [$f->cliente?->nombreMostrar() ?? 'General', $f->valor, $f->cantidad, $f->subtotal]), ['TOTAL', '', $l->fises->sum('cantidad'), $l->total_fises]);
        }
        if ($l->gastos->isNotEmpty()) {
            $reporte->tabla('Varios', ['Concepto' => 'texto', 'Comprobante' => 'texto', 'Monto' => 'decimal'],
                $l->gastos->map(fn ($g) => [$g->concepto, $g->comprobante, $g->monto]), ['TOTAL', '', $l->total_gastos]);
        }

        return $reporte->tabla('Resumen', ['Venta total' => 'decimal', 'Cobranza' => 'decimal', 'Crédito' => 'decimal', 'Varios' => 'decimal', 'FISE' => 'decimal',
            'Vouchers' => 'decimal', 'Por depositar' => 'decimal', 'Entregado' => 'decimal', 'Diferencia' => 'decimal'],
            [[$l->total_venta, $l->total_cobranzas, $l->total_credito, $l->total_gastos, $l->total_fises, $l->total_vouchers, $l->efectivo_esperado, $l->efectivo_entregado, $l->diferencia]]);
    }

    /** Stock real del almacén hoy (igual a la pantalla de stock): llenos, cambios, total y vacíos. */
    private function stockActual(): array
    {
        $c = $this->almacen->controlDelDia(today());
        $total = AlmacenService::totalesPorPresentacion($c);
        $vacios = AlmacenService::totalesVacios($c);

        return [
            'fecha' => today()->format('d/m/Y'),
            'filas' => [
                ['Llenos', $c['lleno_s10']['final'], $c['lleno_s45']['final'], $c['lleno_m10']['final']],
                ['Cambios', $c['cambio_s10']['final'], $c['cambio_s45']['final'], $c['cambio_m10']['final']],
                ['Total', $total['S10'], $total['S45'], $total['M10']],
                ['Vacíos', $vacios['S10'], $vacios['S45'], null],
            ],
        ];
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
                'metodo_pago' => $i->metodo_pago->value, 'monto_credito' => (float) $i->monto_credito ?: '',
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
            'stock' => $this->stockActual(),
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
