<?php

namespace App\Services;

use App\Enums\CategoriaCaja;
use App\Enums\EstadoCuenta;
use App\Enums\EstadoLiquidacion;
use App\Enums\MetodoPago;
use App\Models\CajaMovimiento;
use App\Models\Cobranza;
use App\Models\CuentaPorCobrar;
use App\Models\Liquidacion;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de la liquidación diaria.
 *
 *   Por depositar       = Venta total + Cobranzas − Créditos − Vouchers (Yape/Plin/transferencias) − FISE − Gastos
 *   Efectivo a entregar = Por depositar − Depósitos (BCP, Yape... que el chofer ya depositó)
 */
class LiquidacionService
{
    public function __construct(
        private readonly CuentaService $cuentas,
        private readonly CajaService $caja,
        private readonly PrecioService $precios,
        private readonly CostoService $costos,
    ) {}

    /** Crea o actualiza una liquidación en borrador con todo su detalle. */
    public function guardar(?Liquidacion $liquidacion, array $data): Liquidacion
    {
        if ($liquidacion && ! $liquidacion->esEditable()) {
            throw ValidationException::withMessages(['estado' => 'La liquidación ya está cerrada; reábrela para modificarla.']);
        }

        return DB::transaction(function () use ($liquidacion, $data) {
            $cabecera = [
                'fecha_venta' => $data['fecha_venta'],
                'fecha_liquidacion' => $data['fecha_liquidacion'],
                'chofer_id' => $data['chofer_id'],
                'vehiculo_id' => $data['vehiculo_id'] ?? null,
                'tipo' => $data['tipo'],
                'efectivo_entregado' => $data['efectivo_entregado'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
            ];

            if ($liquidacion) {
                $liquidacion->update($cabecera);
            } else {
                $liquidacion = Liquidacion::create($cabecera + [
                    'codigo' => 'TMP-'.Str::random(12),
                    'estado' => EstadoLiquidacion::Borrador,
                    'user_id' => Auth::id(),
                ]);
                $liquidacion->updateQuietly(['codigo' => $this->codigoPara($liquidacion->id)]);
            }

            // El precio no se digita: es el vigente del cliente a la fecha de venta (como el BUSCARV del Excel).
            $items = array_values($data['items'] ?? []);
            $vigentes = $this->precios->preciosVentaVigentes(array_unique(array_column($items, 'cliente_id')), $data['fecha_venta']);
            // Las filas solo de devolución de vacíos (cantidad 0) no necesitan precio.
            $sinPrecio = array_filter($items, fn ($item) => (int) $item['cantidad'] > 0 && ! isset($vigentes[$item['cliente_id']][$item['producto_id']]));
            if ($sinPrecio) {
                throw ValidationException::withMessages(['items' => count($sinPrecio).' venta(s) sin precio vigente para el cliente. Regístralo en «Precios de venta».']);
            }

            $liquidacion->items()->delete();
            foreach ($items as $i => $item) {
                $cantidad = (int) $item['cantidad'];
                $precio = round((float) ($vigentes[$item['cliente_id']][$item['producto_id']] ?? 0), 2);
                $total = round($cantidad * $precio, 2);
                $credito = ! empty($item['es_credito'])
                    ? round(min($total, (float) ($item['monto_credito'] ?? $total) ?: $total), 2)
                    : 0;

                $liquidacion->items()->create([
                    'cliente_id' => $item['cliente_id'],
                    'empresa_id' => $item['empresa_id'],
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'total' => $total,
                    'costo_unitario' => $this->costos->costoUnitario((int) $item['empresa_id'], (int) $item['producto_id'], $data['fecha_venta']),
                    'vacios_devueltos' => (int) ($item['vacios_devueltos'] ?? 0),
                    'metodo_pago' => $item['metodo_pago'] ?? MetodoPago::Efectivo->value,
                    'monto_credito' => $credito,
                    'numero_operacion' => $item['numero_operacion'] ?? null,
                    'observacion' => $item['observacion'] ?? null,
                    'orden' => $i,
                ]);
            }

            $liquidacion->fises()->delete();
            foreach ($data['fises'] ?? [] as $fise) {
                if ((int) $fise['cantidad'] <= 0) {
                    continue;
                }
                $liquidacion->fises()->create([
                    'cliente_id' => $fise['cliente_id'] ?? null,
                    'valor' => $fise['valor'],
                    'cantidad' => (int) $fise['cantidad'],
                    'subtotal' => round((float) $fise['valor'] * (int) $fise['cantidad'], 2),
                ]);
            }

            $liquidacion->cobranzas()->delete();
            foreach ($data['cobranzas'] ?? [] as $cobranza) {
                $liquidacion->cobranzas()->create([
                    'cliente_id' => $cobranza['cliente_id'],
                    'monto' => round((float) $cobranza['monto'], 2),
                    'metodo_pago' => $cobranza['metodo_pago'] ?? MetodoPago::Efectivo->value,
                    'numero_operacion' => $cobranza['numero_operacion'] ?? null,
                ]);
            }

            $liquidacion->gastos()->delete();
            foreach ($data['gastos'] ?? [] as $gasto) {
                $liquidacion->gastos()->create([
                    'concepto' => $gasto['concepto'],
                    'monto' => round((float) $gasto['monto'], 2),
                    'comprobante' => $gasto['comprobante'] ?? null,
                ]);
            }

            $liquidacion->depositos()->delete();
            foreach ($data['depositos'] ?? [] as $deposito) {
                $liquidacion->depositos()->create([
                    'destino' => $deposito['destino'],
                    'numero_operacion' => $deposito['numero_operacion'] ?? null,
                    'monto' => round((float) $deposito['monto'], 2),
                ]);
            }

            return $this->recalcular($liquidacion);
        });
    }

    /** Recalcula y guarda los totales a partir del detalle. */
    public function recalcular(Liquidacion $liquidacion): Liquidacion
    {
        $liquidacion->load(['items', 'fises', 'cobranzas', 'gastos', 'depositos']);
        $totales = $this->calcular(
            $liquidacion->items->map(fn ($i) => [
                'total' => (float) $i->total,
                'monto_credito' => (float) $i->monto_credito,
                'metodo_pago' => $i->metodo_pago->value,
            ])->all(),
            $liquidacion->fises->map(fn ($f) => ['subtotal' => (float) $f->subtotal])->all(),
            $liquidacion->cobranzas->map(fn ($c) => ['monto' => (float) $c->monto, 'metodo_pago' => $c->metodo_pago->value])->all(),
            $liquidacion->gastos->map(fn ($g) => ['monto' => (float) $g->monto])->all(),
        );

        $totales['total_depositos'] = round((float) $liquidacion->depositos->sum('monto'), 2);
        $entregado = $liquidacion->efectivo_entregado;
        $totales['diferencia'] = $entregado === null ? 0 : round((float) $entregado - $this->efectivoAEntregar($totales), 2);

        $liquidacion->updateQuietly($totales);

        return $liquidacion;
    }

    /**
     * Fórmula de la liquidación (la misma que usa la pantalla en vivo).
     *
     * @return array{total_venta: float, total_credito: float, total_vouchers: float, total_fises: float, total_cobranzas: float, total_cobranzas_efectivo: float, total_gastos: float, efectivo_esperado: float}
     */
    public function calcular(array $items, array $fises, array $cobranzas, array $gastos): array
    {
        $venta = $credito = $vouchers = 0.0;
        foreach ($items as $item) {
            $venta += $item['total'];
            $credito += $item['monto_credito'];
            if ($item['metodo_pago'] !== MetodoPago::Efectivo->value) {
                $vouchers += $item['total'] - $item['monto_credito'];
            }
        }

        $cobranzasTotal = $cobranzasEfectivo = 0.0;
        foreach ($cobranzas as $c) {
            $cobranzasTotal += $c['monto'];
            if ($c['metodo_pago'] === MetodoPago::Efectivo->value) {
                $cobranzasEfectivo += $c['monto'];
            } else {
                $vouchers += $c['monto'];
            }
        }

        $totalFises = array_sum(array_column($fises, 'subtotal'));
        $totalGastos = array_sum(array_column($gastos, 'monto'));

        return [
            'total_venta' => round($venta, 2),
            'total_credito' => round($credito, 2),
            'total_vouchers' => round($vouchers, 2),
            'total_fises' => round($totalFises, 2),
            'total_cobranzas' => round($cobranzasTotal, 2),
            'total_cobranzas_efectivo' => round($cobranzasEfectivo, 2),
            'total_gastos' => round($totalGastos, 2),
            'efectivo_esperado' => round($venta + $cobranzasTotal - $credito - $vouchers - $totalFises - $totalGastos, 2),
        ];
    }

    /** Efectivo que el chofer debe entregar: lo por depositar menos lo que ya depositó. */
    public function efectivoAEntregar(array|Liquidacion $totales): float
    {
        $t = $totales instanceof Liquidacion ? $totales->only(['efectivo_esperado', 'total_depositos']) : $totales;

        return round((float) $t['efectivo_esperado'] - (float) ($t['total_depositos'] ?? 0), 2);
    }

    /**
     * Cierra la liquidación: genera las cuentas por cobrar, aplica las cobranzas
     * y registra el ingreso de efectivo en caja.
     */
    public function cerrar(Liquidacion $liquidacion, ?float $efectivoEntregado = null): Liquidacion
    {
        if (! $liquidacion->esEditable()) {
            throw ValidationException::withMessages(['estado' => 'Solo se puede cerrar una liquidación en borrador.']);
        }

        return DB::transaction(function () use ($liquidacion, $efectivoEntregado) {
            $liquidacion->load(['items.cliente', 'cobranzas', 'chofer']);

            if ($liquidacion->items->isEmpty() && $liquidacion->cobranzas->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'La liquidación no tiene ventas ni cobranzas.']);
            }

            if ($efectivoEntregado !== null) {
                $liquidacion->efectivo_entregado = $efectivoEntregado;
            }
            $this->recalcular($liquidacion);

            foreach ($liquidacion->items as $item) {
                if ((float) $item->monto_credito > 0) {
                    CuentaPorCobrar::create([
                        'cliente_id' => $item->cliente_id,
                        'liquidacion_id' => $liquidacion->id,
                        'liquidacion_item_id' => $item->id,
                        'fecha' => $liquidacion->fecha_venta,
                        'monto' => $item->monto_credito,
                        'saldo' => $item->monto_credito,
                        'estado' => EstadoCuenta::Pendiente,
                        'observaciones' => "{$liquidacion->codigo} · {$item->cantidad} {$item->producto?->codigo}",
                    ]);
                }
            }

            foreach ($liquidacion->cobranzas as $cobranza) {
                $this->cuentas->aplicarPago(
                    $cobranza->cliente_id,
                    (float) $cobranza->monto,
                    $liquidacion->fecha_venta,
                    $cobranza->metodo_pago,
                    $cobranza->numero_operacion,
                    $cobranza->id,
                );
            }

            $efectivo = (float) ($liquidacion->efectivo_entregado ?? $this->efectivoAEntregar($liquidacion));
            $this->caja->registrarPara(
                $liquidacion,
                $efectivo >= 0 ? CajaMovimiento::INGRESO : CajaMovimiento::EGRESO,
                CategoriaCaja::Liquidacion,
                abs($efectivo),
                $liquidacion->fecha_liquidacion,
                "Efectivo {$liquidacion->codigo} · {$liquidacion->chofer->alias}",
            );

            $liquidacion->update([
                'estado' => EstadoLiquidacion::Cerrada,
                'cerrada_por' => Auth::id(),
                'cerrada_at' => now(),
            ]);
            AuditLogger::event('cerrada', "Cerró la liquidación {$liquidacion->codigo}", $liquidacion, [
                'efectivo_esperado' => $liquidacion->efectivo_esperado,
                'efectivo_entregado' => $liquidacion->efectivo_entregado,
            ]);

            return $liquidacion;
        });
    }

    /** Vuelve a borrador deshaciendo créditos, cobranzas y caja. */
    public function reabrir(Liquidacion $liquidacion): Liquidacion
    {
        if ($liquidacion->estado !== EstadoLiquidacion::Cerrada) {
            throw ValidationException::withMessages(['estado' => 'Solo se puede reabrir una liquidación cerrada.']);
        }

        return DB::transaction(function () use ($liquidacion) {
            $cobranzaIds = $liquidacion->cobranzas()->pluck('id');
            $cuentas = $liquidacion->cuentasPorCobrar()->get();

            $pagosExternos = Cobranza::whereIn('cuenta_por_cobrar_id', $cuentas->pluck('id'))
                ->where(fn ($q) => $q->whereNull('liquidacion_cobranza_id')->orWhereNotIn('liquidacion_cobranza_id', $cobranzaIds))
                ->exists();
            if ($pagosExternos) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede reabrir: algún crédito de esta liquidación ya recibió pagos posteriores. Anula primero esas cobranzas.',
                ]);
            }

            Cobranza::whereIn('liquidacion_cobranza_id', $cobranzaIds)->get()
                ->each(fn (Cobranza $c) => $this->cuentas->revertirCobranza($c));
            $cuentas->each->forceDelete();
            $this->caja->eliminarPara($liquidacion);

            $liquidacion->update(['estado' => EstadoLiquidacion::Borrador, 'cerrada_por' => null, 'cerrada_at' => null]);
            AuditLogger::event('reabierta', "Reabrió la liquidación {$liquidacion->codigo}", $liquidacion);

            return $liquidacion;
        });
    }

    public function anular(Liquidacion $liquidacion, string $motivo): Liquidacion
    {
        if (! $liquidacion->esEditable()) {
            throw ValidationException::withMessages(['estado' => 'Reabre la liquidación antes de anularla.']);
        }
        $liquidacion->update([
            'estado' => EstadoLiquidacion::Anulada,
            'observaciones' => trim(($liquidacion->observaciones ?? '')."\nAnulada: ".$motivo),
        ]);
        AuditLogger::event('anulada', "Anuló la liquidación {$liquidacion->codigo}: {$motivo}", $liquidacion);

        return $liquidacion;
    }

    public function codigoPara(int $id): string
    {
        return 'LIQ-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
