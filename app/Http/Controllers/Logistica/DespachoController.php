<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoLiquidacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\DespachoRequest;
use App\Http\Requests\RetornoDespachoRequest;
use App\Models\Chofer;
use App\Models\Despacho;
use App\Models\Empresa;
use App\Models\LiquidacionItem;
use App\Models\Producto;
use App\Models\Vehiculo;
use App\Services\LogisticaService;
use App\Services\StockService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Salidas de balones a los choferes y su retorno (varias vueltas por día).
 */
class DespachoController extends Controller
{
    public function __construct(
        private readonly LogisticaService $logistica,
        private readonly StockService $stock,
    ) {}

    public function index(Request $request)
    {
        $despachos = Despacho::with(['chofer', 'vehiculo', 'detalles.producto', 'detalles.empresa'])
            ->when($request->chofer_id, fn ($q, $c) => $q->where('chofer_id', $c))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha, fn ($q, $f) => $q->where('fecha', $f))
            ->orderByDesc('fecha')->orderBy('chofer_id')->orderBy('vuelta')->paginate(30)->withQueryString();

        $enRuta = Despacho::where('estado', EstadoDespacho::EnRuta)->with('chofer')->get();
        $choferes = Chofer::activos()->pluck('alias', 'id');

        return $this->tableOrPage($request, 'logistica.despachos.index', 'logistica.despachos._table', compact('despachos', 'enRuta', 'choferes'));
    }

    public function create(): View
    {
        return $this->form(new Despacho(['fecha' => today(), 'vuelta' => 1, 'hora_salida' => now()->format('H:i')]));
    }

    public function store(DespachoRequest $request): JsonResponse
    {
        $despacho = DB::transaction(function () use ($request) {
            $despacho = Despacho::create($request->safe()->except('detalles') + [
                'estado' => EstadoDespacho::EnRuta,
                'user_id' => Auth::id(),
            ]);
            foreach ($request->lineas() as $linea) {
                $despacho->detalles()->create($linea);
            }
            $this->logistica->aplicarDespacho($despacho);

            return $despacho;
        });

        return $this->ok("Despacho registrado: {$despacho->chofer->alias} sale con {$despacho->totalSalida()} balones (vuelta {$despacho->vuelta}).");
    }

    public function show(Despacho $despacho): View
    {
        $despacho->load(['chofer', 'vehiculo', 'user', 'detalles.producto', 'detalles.empresa', 'movimientos.producto', 'movimientos.empresa']);

        // Cuadre con liquidaciones: lo que logística dice que vendió vs. lo que se liquidó ese día.
        $despachosDia = Despacho::with('detalles')->where('chofer_id', $despacho->chofer_id)->where('fecha', $despacho->fecha)
            ->where('estado', EstadoDespacho::Retornado)->get();
        $vendidoLogistica = [];
        foreach ($despachosDia as $d) {
            foreach ($d->detalles as $det) {
                $vendidoLogistica[$det->producto_id] = ($vendidoLogistica[$det->producto_id] ?? 0) + $det->vendidos();
            }
        }
        $liquidado = LiquidacionItem::query()
            ->whereHas('liquidacion', fn ($q) => $q->where('chofer_id', $despacho->chofer_id)->where('fecha_venta', $despacho->fecha)->where('estado', '!=', EstadoLiquidacion::Anulada))
            ->selectRaw('producto_id, SUM(cantidad) as cantidad')->groupBy('producto_id')->pluck('cantidad', 'producto_id');
        $productos = Producto::whereIn('id', array_unique(array_merge(array_keys($vendidoLogistica), $liquidado->keys()->all())))->get();

        return view('logistica.despachos.show', compact('despacho', 'vendidoLogistica', 'liquidado', 'productos', 'despachosDia'));
    }

    public function edit(Despacho $despacho): View
    {
        abort_if($despacho->estado === EstadoDespacho::Anulado, 422, 'El despacho está anulado.');

        return $this->form($despacho);
    }

    public function update(DespachoRequest $request, Despacho $despacho): JsonResponse
    {
        abort_if($despacho->estado === EstadoDespacho::Anulado, 422, 'El despacho está anulado.');

        DB::transaction(function () use ($request, $despacho) {
            $despacho->update($request->safe()->except('detalles'));
            $ids = [];
            foreach ($request->lineas() as $linea) {
                $detalle = $despacho->detalles()->updateOrCreate(
                    ['empresa_id' => $linea['empresa_id'], 'producto_id' => $linea['producto_id']],
                    ['llenos_salida' => $linea['llenos_salida']],
                );
                $ids[] = $detalle->id;
            }
            $despacho->detalles()->whereNotIn('id', $ids)->delete();
            $this->logistica->aplicarDespacho($despacho->fresh());
        });

        return $this->ok('Despacho actualizado.');
    }

    public function retornoForm(Despacho $despacho): View
    {
        abort_if($despacho->estado === EstadoDespacho::Anulado, 422, 'El despacho está anulado.');
        $despacho->load(['chofer', 'detalles.producto', 'detalles.empresa']);

        return view('logistica.despachos.retorno', compact('despacho'));
    }

    public function retorno(RetornoDespachoRequest $request, Despacho $despacho): JsonResponse
    {
        DB::transaction(function () use ($request, $despacho) {
            foreach ($despacho->detalles as $detalle) {
                $input = $request->input("detalles.{$detalle->id}", []);
                $detalle->update([
                    'llenos_retorno' => (int) ($input['llenos_retorno'] ?? 0),
                    'vacios_retorno' => (int) ($input['vacios_retorno'] ?? 0),
                    'colores_retorno' => (int) ($input['colores_retorno'] ?? 0),
                    'cambios_retorno' => (int) ($input['cambios_retorno'] ?? 0),
                ]);
            }
            $despacho->update([
                'estado' => EstadoDespacho::Retornado,
                'hora_retorno' => $request->hora_retorno ?: now()->format('H:i'),
                'observaciones' => $request->observaciones ?? $despacho->observaciones,
            ]);
            $this->logistica->aplicarDespacho($despacho->fresh());
            AuditLogger::event('retornado', "Registró el retorno de {$despacho->chofer->alias} (vuelta {$despacho->vuelta})", $despacho);
        });

        $despacho->refresh()->load('detalles');

        return $this->ok("Retorno registrado: {$despacho->totalVendidos()} balones vendidos, {$despacho->totalVacios()} vacíos recogidos.");
    }

    public function anular(Request $request, Despacho $despacho): JsonResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'max:200']]);
        DB::transaction(function () use ($request, $despacho) {
            $despacho->update([
                'estado' => EstadoDespacho::Anulado,
                'observaciones' => trim(($despacho->observaciones ?? '')."\nAnulado: ".$request->motivo),
            ]);
            $this->logistica->aplicarDespacho($despacho);
            AuditLogger::event('anulada', "Anuló el despacho de {$despacho->chofer->alias}: {$request->motivo}", $despacho);
        });

        return $this->ok('Despacho anulado; su stock fue revertido.', ['closeModal' => true]);
    }

    public function destroy(Despacho $despacho): JsonResponse
    {
        DB::transaction(function () use ($despacho) {
            $despacho->movimientos()->delete();
            $despacho->delete();
        });

        return $this->ok('Despacho eliminado y stock revertido.');
    }

    private function form(Despacho $despacho): View
    {
        $despacho->loadMissing('detalles');
        $choferes = Chofer::activos()->get();
        $empresas = Empresa::activas()->get();
        $productos = Producto::activos()->where('controla_stock', true)->whereIn('tipo', [Producto::TIPO_GAS, Producto::TIPO_ENVASE])->get();

        return view('logistica.despachos.form', [
            'despacho' => $despacho,
            'choferes' => $choferes->mapWithKeys(fn ($c) => [$c->id => $c->alias.' · '.$c->tipo->label()]),
            'vehiculosPorChofer' => $choferes->pluck('vehiculo_id', 'id'),
            'tiposPorChofer' => $choferes->mapWithKeys(fn ($c) => [$c->id => $c->tipo->value]),
            'vehiculos' => Vehiculo::operativos()->pluck('placa', 'id'),
            'empresas' => $empresas,
            'productos' => $productos,
            'stock' => $this->stock->saldos(),
        ]);
    }
}
