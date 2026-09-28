<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoGuia;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuiaRequest;
use App\Http\Requests\RecepcionGuiaRequest;
use App\Models\Chofer;
use App\Models\Empresa;
use App\Models\Guia;
use App\Models\Instalacion;
use App\Models\Producto;
use App\Models\Vehiculo;
use App\Services\LogisticaService;
use App\Services\PrecioService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Guías de abastecimiento en la planta de Solgas (movimiento de masa).
 */
class GuiaController extends Controller
{
    public function __construct(
        private readonly LogisticaService $logistica,
        private readonly PrecioService $precios,
    ) {}

    public function index(Request $request)
    {
        $guias = Guia::with(['empresa', 'instalacion', 'vehiculo', 'chofer', 'detalles.producto'])
            ->when($request->q, fn ($q, $t) => $q->where('numero_guia', 'like', "%$t%"))
            ->when($request->empresa_id, fn ($q, $e) => $q->where('empresa_id', $e))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->desde, fn ($q, $d) => $q->where('fecha_salida', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->where('fecha_salida', '<=', $h))
            ->orderByDesc('fecha_salida')->orderByDesc('id')->paginate(25)->withQueryString();

        $enTransito = Guia::where('estado', EstadoGuia::EnTransito)->count();
        $empresas = Empresa::activas()->pluck('nombre', 'id');

        return $this->tableOrPage($request, 'logistica.guias.index', 'logistica.guias._table', compact('guias', 'enTransito', 'empresas'));
    }

    public function create(): View
    {
        return $this->form(new Guia(['fecha_salida' => today(), 'estado' => EstadoGuia::EnTransito]));
    }

    public function store(GuiaRequest $request): JsonResponse
    {
        $guia = DB::transaction(function () use ($request) {
            $guia = Guia::create($request->safe()->except(['detalles', 'acepto_diferencia']) + [
                'estado' => EstadoGuia::EnTransito,
                'user_id' => Auth::id(),
            ]);
            $this->guardarDetalles($guia, $request->detallesConDatos());
            $this->logistica->aplicarGuia($guia);

            return $guia;
        });

        return $this->ok("Guía {$guia->numero_guia} registrada. Salieron {$guia->totalEnviado()} balones a planta.");
    }

    public function show(Guia $guia): View
    {
        $guia->load(['empresa', 'instalacion', 'vehiculo', 'chofer', 'user', 'detalles.producto', 'movimientos.producto', 'movimientos.empresa']);

        return view('logistica.guias.show', compact('guia'));
    }

    public function edit(Guia $guia): View
    {
        abort_if($guia->estado === EstadoGuia::Anulada, 422, 'La guía está anulada.');

        return $this->form($guia);
    }

    public function update(GuiaRequest $request, Guia $guia): JsonResponse
    {
        abort_if($guia->estado === EstadoGuia::Anulada, 422, 'La guía está anulada.');

        DB::transaction(function () use ($request, $guia) {
            $guia->update($request->safe()->except(['detalles', 'acepto_diferencia']));
            $this->guardarDetalles($guia, $request->detallesConDatos());
            $this->logistica->aplicarGuia($guia->fresh());
        });

        return $this->ok("Guía {$guia->numero_guia} actualizada.");
    }

    public function recibirForm(Guia $guia): View
    {
        abort_if($guia->estado === EstadoGuia::Anulada, 422, 'La guía está anulada.');
        $guia->load(['detalles.producto', 'empresa', 'instalacion']);

        return view('logistica.guias.recibir', compact('guia'));
    }

    public function recibir(RecepcionGuiaRequest $request, Guia $guia): JsonResponse
    {
        DB::transaction(function () use ($request, $guia) {
            foreach ($guia->detalles as $detalle) {
                $input = $request->input("detalles.{$detalle->id}", []);
                $detalle->update([
                    'llenos_recibidos' => (int) ($input['llenos_recibidos'] ?? 0),
                    'cambios_repuestos' => (int) ($input['cambios_repuestos'] ?? 0),
                    'vacios_rechazados' => (int) ($input['vacios_rechazados'] ?? 0),
                    'colores_rechazados' => (int) ($input['colores_rechazados'] ?? 0),
                ]);
            }
            $guia->update([
                'estado' => EstadoGuia::Recibida,
                'fecha_recepcion' => $request->fecha_recepcion,
                'observaciones' => $request->observaciones ?? $guia->observaciones,
            ]);
            $this->logistica->aplicarGuia($guia->fresh());
            AuditLogger::event('recibida', "Recibió la guía {$guia->numero_guia}", $guia, ['llenos' => $guia->fresh('detalles')->totalLlenos()]);
        });

        return $this->ok("Guía {$guia->numero_guia} recibida. Ingresaron {$guia->fresh('detalles')->totalLlenos()} balones llenos.");
    }

    public function anular(Request $request, Guia $guia): JsonResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'max:200']]);
        DB::transaction(function () use ($request, $guia) {
            $guia->update([
                'estado' => EstadoGuia::Anulada,
                'observaciones' => trim(($guia->observaciones ?? '')."\nAnulada: ".$request->motivo),
            ]);
            $this->logistica->aplicarGuia($guia);
            AuditLogger::event('anulada', "Anuló la guía {$guia->numero_guia}: {$request->motivo}", $guia);
        });

        return $this->ok("Guía {$guia->numero_guia} anulada; su stock fue revertido.", ['closeModal' => true]);
    }

    public function destroy(Guia $guia): JsonResponse
    {
        DB::transaction(function () use ($guia) {
            $guia->movimientos()->delete();
            $guia->delete();
        });

        return $this->ok("Guía {$guia->numero_guia} eliminada y stock revertido.");
    }

    private function guardarDetalles(Guia $guia, array $detalles): void
    {
        $ids = [];
        foreach ($detalles as $d) {
            $precio = $d['precio_compra'] ?? null;
            if ($precio === null || $precio === '') {
                $precio = $this->precios->precioCompra($guia->instalacion_id, (int) $d['producto_id'], $guia->fecha_salida) ?? 0;
            }
            $detalle = $guia->detalles()->updateOrCreate(['producto_id' => $d['producto_id']], [
                'cantidad_guia' => (int) ($d['cantidad_guia'] ?? 0),
                'precio_compra' => $precio,
                'vacios_enviados' => (int) ($d['vacios_enviados'] ?? 0),
                'colores_enviados' => (int) ($d['colores_enviados'] ?? 0),
                'cambios_enviados' => (int) ($d['cambios_enviados'] ?? 0),
            ]);
            $ids[] = $detalle->id;
        }
        $guia->detalles()->whereNotIn('id', $ids)->delete();

        if ($guia->estado === EstadoGuia::Recibida) {
            foreach ($guia->detalles()->get() as $detalle) {
                if ($detalle->totalRetornado() !== $detalle->totalEnviado()) {
                    throw ValidationException::withMessages(['detalles' => "La guía ya fue recibida: al cambiar lo enviado de {$detalle->producto->codigo} el movimiento de masa deja de cuadrar. Corrige también el retorno."]);
                }
            }
        }
    }

    private function form(Guia $guia): View
    {
        $guia->loadMissing('detalles');
        $instalaciones = Instalacion::activas()->with(['chofer', 'vehiculo'])->get();
        $vigentes = $this->precios->preciosCompraVigentes($guia->fecha_salida);

        $precios = [];
        foreach ($vigentes as $instalacionId => $porProducto) {
            foreach ($porProducto as $productoId => $precio) {
                $precios[$instalacionId][$productoId] = (float) $precio->precio;
            }
        }

        return view('logistica.guias.form', [
            'guia' => $guia,
            'empresas' => Empresa::activas()->get(),
            'instalaciones' => $instalaciones,
            'productos' => Producto::dePlanta()->get(),
            'vehiculos' => Vehiculo::operativos()->pluck('placa', 'id'),
            'choferes' => Chofer::activos()->pluck('alias', 'id'),
            'precios' => $precios,
        ]);
    }
}
