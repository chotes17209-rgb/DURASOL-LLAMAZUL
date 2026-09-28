<?php

namespace App\Http\Controllers\Ventas;

use App\Enums\CategoriaCaja;
use App\Enums\EstadoCuenta;
use App\Enums\MetodoPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\CobranzaRequest;
use App\Models\CajaMovimiento;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\CuentaPorCobrar;
use App\Services\CajaService;
use App\Services\CuentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cuentas por cobrar (ventas al crédito) y cobranzas registradas en oficina.
 */
class CreditoController extends Controller
{
    public function __construct(
        private readonly CuentaService $cuentas,
        private readonly CajaService $caja,
    ) {}

    public function index(Request $request)
    {
        $vista = $request->vista === 'detalle' ? 'detalle' : 'clientes';

        if ($vista === 'clientes') {
            $registros = Cliente::query()->with('chofer')
                ->buscar($request->q)
                ->when($request->chofer_id, fn ($q, $c) => $q->where('chofer_id', $c))
                ->whereHas('cuentasPorCobrar', fn ($q) => $q->pendientes())
                ->withSum(['cuentasPorCobrar as deuda' => fn ($q) => $q->pendientes()], 'saldo')
                ->withCount(['cuentasPorCobrar as documentos' => fn ($q) => $q->pendientes()])
                ->withMin(['cuentasPorCobrar as desde' => fn ($q) => $q->pendientes()], 'fecha')
                ->orderByDesc('deuda')->paginate(30)->withQueryString();
        } else {
            $registros = CuentaPorCobrar::with(['cliente.chofer', 'liquidacion'])
                ->when($request->estado, fn ($q, $e) => $q->where('estado', $e), fn ($q) => $q->pendientes())
                ->when($request->q, fn ($q, $t) => $q->whereHas('cliente', fn ($c) => $c->buscar($t)))
                ->when($request->chofer_id, fn ($q, $c) => $q->whereHas('cliente', fn ($cl) => $cl->where('chofer_id', $c)))
                ->orderBy('fecha')->paginate(40)->withQueryString();
        }

        $totalPendiente = (float) CuentaPorCobrar::pendientes()->sum('saldo');
        $clientesConDeuda = CuentaPorCobrar::pendientes()->distinct('cliente_id')->count('cliente_id');
        $vencidas30 = (float) CuentaPorCobrar::pendientes()->where('fecha', '<', today()->subDays(30)->toDateString())->sum('saldo');
        $cobradoMes = (float) Cobranza::where('fecha', '>=', today()->startOfMonth()->toDateString())->sum('monto');
        $choferes = Chofer::vendedores()->pluck('alias', 'id');

        return $this->tableOrPage($request, 'ventas.creditos.index', 'ventas.creditos._table',
            compact('vista', 'registros', 'totalPendiente', 'clientesConDeuda', 'vencidas30', 'cobradoMes', 'choferes'));
    }

    public function cliente(Cliente $cliente): View
    {
        $cuentas = $cliente->cuentasPorCobrar()->with(['cobranzas.user', 'liquidacion'])->orderByDesc('fecha')->limit(100)->get();
        $cobranzas = Cobranza::with(['cuentaPorCobrar', 'user', 'liquidacionCobranza.liquidacion'])->where('cliente_id', $cliente->id)->latest('fecha')->limit(100)->get();

        return view('ventas.creditos.cliente', compact('cliente', 'cuentas', 'cobranzas'));
    }

    public function show(CuentaPorCobrar $cuenta): View
    {
        $cuenta->load(['cliente', 'liquidacion.chofer', 'cobranzas.user']);

        return view('ventas.creditos.show', compact('cuenta'));
    }

    public function createCobranza(Request $request): View
    {
        $cliente = $request->cliente_id ? Cliente::find($request->cliente_id) : null;

        return view('ventas.creditos.cobranza', [
            'cliente' => $cliente,
            'deuda' => $cliente ? $this->cuentas->deudaCliente($cliente->id) : null,
        ]);
    }

    /** Cobranza en oficina: se aplica a las deudas más antiguas y, si es en efectivo, entra a caja. */
    public function storeCobranza(CobranzaRequest $request): JsonResponse
    {
        $metodo = MetodoPago::from($request->metodo_pago);
        $cobranzas = DB::transaction(function () use ($request, $metodo) {
            $cobranzas = $this->cuentas->aplicarPago((int) $request->cliente_id, (float) $request->monto, $request->fecha, $metodo, $request->numero_operacion);
            if ($metodo === MetodoPago::Efectivo) {
                $cliente = Cliente::find($request->cliente_id);
                foreach ($cobranzas as $cobranza) {
                    $this->caja->registrarPara($cobranza, CajaMovimiento::INGRESO, CategoriaCaja::Cobranza, (float) $cobranza->monto, $cobranza->fecha, "Cobranza a {$cliente->nombre}");
                }
            }

            return $cobranzas;
        });

        return $this->ok('Cobranza registrada por '.soles($cobranzas->sum('monto')).' y aplicada a '.$cobranzas->count().' deuda(s).', ['reloadModal' => true]);
    }

    public function destroyCobranza(Cobranza $cobranza): JsonResponse
    {
        abort_if($cobranza->liquidacion_cobranza_id !== null, 422, 'Esta cobranza vino de una liquidación; para quitarla reabre esa liquidación.');
        DB::transaction(function () use ($cobranza) {
            $this->caja->eliminarPara($cobranza);
            $this->cuentas->revertirCobranza($cobranza);
        });

        return $this->ok('Cobranza anulada; la deuda volvió a quedar pendiente.', ['reloadModal' => true]);
    }
}
