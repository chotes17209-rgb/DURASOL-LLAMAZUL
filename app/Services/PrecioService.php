<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\PrecioCompra;
use App\Models\PrecioVenta;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Precios vigentes. Un precio vale desde su "vigente_desde" hasta que aparece otro más nuevo.
 */
class PrecioService
{
    public function precioVenta(int $clienteId, int $productoId, Carbon|string|null $fecha = null): ?float
    {
        $precio = PrecioVenta::where('cliente_id', $clienteId)
            ->where('producto_id', $productoId)
            ->where('vigente_desde', '<=', Carbon::parse($fecha ?? today())->toDateString())
            ->orderByDesc('vigente_desde')->orderByDesc('id')
            ->value('precio');

        return $precio === null ? null : (float) $precio;
    }

    /**
     * Precios vigentes de varios clientes: [cliente_id][producto_id] => precio.
     *
     * @param  array<int>|null  $clienteIds  null = todos
     */
    public function preciosVentaVigentes(?array $clienteIds = null, Carbon|string|null $fecha = null): array
    {
        $fecha = Carbon::parse($fecha ?? today())->toDateString();

        $ultimos = PrecioVenta::query()
            ->selectRaw('MAX(id) as id')
            ->where('vigente_desde', '<=', $fecha)
            ->when($clienteIds !== null, fn ($q) => $q->whereIn('cliente_id', $clienteIds))
            ->whereRaw('vigente_desde = (select max(p2.vigente_desde) from precios_venta p2 where p2.cliente_id = precios_venta.cliente_id and p2.producto_id = precios_venta.producto_id and p2.vigente_desde <= ?)', [$fecha])
            ->groupBy('cliente_id', 'producto_id');

        $mapa = [];
        PrecioVenta::whereIn('id', $ultimos)->get(['cliente_id', 'producto_id', 'precio'])
            ->each(function ($p) use (&$mapa) {
                $mapa[$p->cliente_id][$p->producto_id] = (float) $p->precio;
            });

        return $mapa;
    }

    public function precioCompra(int $instalacionId, int $productoId, Carbon|string|null $fecha = null): ?float
    {
        $precio = PrecioCompra::where('instalacion_id', $instalacionId)
            ->where('producto_id', $productoId)
            ->where('vigente_desde', '<=', Carbon::parse($fecha ?? today())->toDateString())
            ->orderByDesc('vigente_desde')->orderByDesc('id')
            ->value('precio');

        return $precio === null ? null : (float) $precio;
    }

    /** Precios de compra vigentes: [instalacion_id][producto_id] => PrecioCompra. */
    public function preciosCompraVigentes(Carbon|string|null $fecha = null): array
    {
        $fecha = Carbon::parse($fecha ?? today())->toDateString();
        $mapa = [];
        PrecioCompra::with('user')
            ->where('vigente_desde', '<=', $fecha)
            ->orderBy('vigente_desde')->orderBy('id')
            ->get()
            ->each(function (PrecioCompra $p) use (&$mapa) {
                $mapa[$p->instalacion_id][$p->producto_id] = $p;
            });

        return $mapa;
    }

    /**
     * Sube o baja el precio de un producto a muchos clientes a la vez
     * (por ejemplo, cuando Solgas cambia su precio). Crea una fila nueva por cliente.
     *
     * @return int cantidad de clientes actualizados
     */
    public function ajusteMasivoVenta(int $productoId, float $variacion, Carbon $vigenteDesde, ?string $motivo, ?int $choferId = null): int
    {
        $clientes = Cliente::query()
            ->where('activo', true)
            ->when($choferId, fn ($q) => $q->where('chofer_id', $choferId))
            ->pluck('id')->all();

        $vigentes = $this->preciosVentaVigentes($clientes, $vigenteDesde);

        return DB::transaction(function () use ($vigentes, $productoId, $variacion, $vigenteDesde, $motivo) {
            $actualizados = 0;
            foreach ($vigentes as $clienteId => $precios) {
                if (! isset($precios[$productoId]) || $precios[$productoId] <= 0) {
                    continue;
                }
                PrecioVenta::create([
                    'cliente_id' => $clienteId,
                    'producto_id' => $productoId,
                    'precio' => round(max(0, $precios[$productoId] + $variacion), 2),
                    'vigente_desde' => $vigenteDesde->toDateString(),
                    'motivo' => $motivo ?: sprintf('Ajuste masivo %+.2f', $variacion),
                    'user_id' => Auth::id(),
                ]);
                $actualizados++;
            }

            return $actualizados;
        });
    }

    /**
     * Guarda los precios de venta de un cliente. Solo crea una fila nueva cuando el
     * precio cambia respecto al vigente, así el historial refleja cambios reales.
     *
     * @param  array<int|string, mixed>  $precios  producto_id => precio (vacío = sin cambio)
     * @return int precios modificados
     */
    public function guardarPreciosVenta(Cliente $cliente, array $precios, Carbon|string $vigenteDesde, ?string $motivo = null): int
    {
        $fecha = Carbon::parse($vigenteDesde)->toDateString();
        $cambios = 0;
        foreach ($precios as $productoId => $precio) {
            if ($precio === null || $precio === '') {
                continue;
            }
            $precio = round((float) $precio, 2);
            $actual = $this->precioVenta($cliente->id, (int) $productoId, $fecha);
            if ($actual !== null && abs($actual - $precio) < 0.001) {
                continue;
            }
            PrecioVenta::create([
                'cliente_id' => $cliente->id,
                'producto_id' => (int) $productoId,
                'precio' => $precio,
                'vigente_desde' => $fecha,
                'motivo' => $motivo ?: ($actual === null ? 'Precio inicial' : 'Cambio de precio'),
                'user_id' => Auth::id(),
            ]);
            $cambios++;
        }

        return $cambios;
    }

    /** Igual que guardarPreciosVenta pero para los precios de compra de una instalación. */
    public function guardarPreciosCompra(Instalacion $instalacion, array $precios, Carbon|string $vigenteDesde, ?string $motivo = null): int
    {
        $fecha = Carbon::parse($vigenteDesde)->toDateString();
        $cambios = 0;
        foreach ($precios as $productoId => $precio) {
            if ($precio === null || $precio === '') {
                continue;
            }
            $precio = round((float) $precio, 2);
            $actual = $this->precioCompra($instalacion->id, (int) $productoId, $fecha);
            if ($actual !== null && abs($actual - $precio) < 0.001) {
                continue;
            }
            PrecioCompra::create([
                'empresa_id' => $instalacion->empresa_id,
                'instalacion_id' => $instalacion->id,
                'producto_id' => (int) $productoId,
                'precio' => $precio,
                'vigente_desde' => $fecha,
                'motivo' => $motivo ?: ($actual === null ? 'Precio inicial' : 'Cambio de precio'),
                'user_id' => Auth::id(),
            ]);
            $cambios++;
        }

        return $cambios;
    }

    /**
     * Datos del cuadro de instalaciones (pantallas de instalaciones y de precios de compra).
     * Filtros: q (código, responsable, placa, nombre), empresa_id, estado (pendiente | inactivas).
     */
    public function cuadroInstalaciones(Request $request): array
    {
        $vigentes = $this->preciosCompraVigentes();
        $instalaciones = Instalacion::with(['empresa', 'chofer', 'vehiculo'])
            ->where('activo', $request->estado !== 'inactivas')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('codigo', 'like', "%$t%")->orWhere('nombre', 'like', "%$t%")
                ->orWhere('responsable', 'like', '%'.mb_strtoupper($t).'%')->orWhere('placas', 'like', '%'.mb_strtoupper($t).'%')))
            ->when($request->empresa_id, fn ($q, $e) => $q->where('empresa_id', $e))
            ->orderBy('empresa_id')->orderBy('responsable')->orderBy('codigo')->get()
            ->when($request->estado === 'pendiente', fn ($c) => $c->filter(fn ($i) => collect($vigentes[$i->id] ?? [])->contains('validado', false)));

        return [
            'instalaciones' => $instalaciones,
            'vigentes' => $vigentes,
            'productos' => Producto::dePlanta()->get(),
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
        ];
    }

    /** Historial de precios de venta de un cliente agrupado por producto. */
    public function historialCliente(Cliente $cliente): Collection
    {
        return $cliente->preciosVenta()->with(['producto', 'user'])
            ->orderByDesc('vigente_desde')->orderByDesc('id')
            ->get()
            ->groupBy('producto_id');
    }
}
