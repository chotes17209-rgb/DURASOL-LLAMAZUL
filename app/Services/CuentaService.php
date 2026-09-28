<?php

namespace App\Services;

use App\Enums\EstadoCuenta;
use App\Enums\MetodoPago;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\CuentaPorCobrar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Créditos a clientes y sus cobranzas. Los pagos se aplican a las deudas más antiguas primero.
 */
class CuentaService
{
    public function deudaCliente(int $clienteId): float
    {
        return round((float) CuentaPorCobrar::where('cliente_id', $clienteId)->pendientes()->sum('saldo'), 2);
    }

    /**
     * Aplica un pago a las deudas pendientes del cliente (FIFO).
     *
     * @return Collection<int, Cobranza>
     *
     * @throws ValidationException si el pago supera la deuda
     */
    public function aplicarPago(int $clienteId, float $monto, Carbon|string $fecha, MetodoPago $metodo, ?string $operacion = null, ?int $liquidacionCobranzaId = null): Collection
    {
        $monto = round($monto, 2);
        $deuda = $this->deudaCliente($clienteId);

        if ($monto <= 0) {
            throw ValidationException::withMessages(['monto' => 'El monto de la cobranza debe ser mayor a cero.']);
        }
        if ($monto > $deuda + 0.001) {
            $cliente = Cliente::withTrashed()->find($clienteId);
            throw ValidationException::withMessages([
                'monto' => sprintf('La cobranza de S/ %.2f supera la deuda de %s (S/ %.2f).', $monto, $cliente?->nombre, $deuda),
            ]);
        }

        $cobranzas = collect();
        $restante = $monto;
        $cuentas = CuentaPorCobrar::where('cliente_id', $clienteId)->pendientes()->orderBy('fecha')->orderBy('id')->lockForUpdate()->get();

        foreach ($cuentas as $cuenta) {
            if ($restante <= 0) {
                break;
            }
            $aplicado = min($restante, (float) $cuenta->saldo);
            $cobranzas->push(Cobranza::create([
                'cuenta_por_cobrar_id' => $cuenta->id,
                'cliente_id' => $clienteId,
                'fecha' => Carbon::parse($fecha)->toDateString(),
                'monto' => $aplicado,
                'metodo_pago' => $metodo,
                'numero_operacion' => $operacion,
                'liquidacion_cobranza_id' => $liquidacionCobranzaId,
                'user_id' => Auth::id(),
            ]));
            $this->recalcular($cuenta);
            $restante = round($restante - $aplicado, 2);
        }

        return $cobranzas;
    }

    /** Elimina una cobranza y devuelve el saldo a su cuenta. */
    public function revertirCobranza(Cobranza $cobranza): void
    {
        $cuenta = $cobranza->cuentaPorCobrar;
        $cobranza->delete();
        if ($cuenta) {
            $this->recalcular($cuenta);
        }
    }

    public function recalcular(CuentaPorCobrar $cuenta): void
    {
        $pagado = (float) $cuenta->cobranzas()->sum('monto');
        $saldo = round((float) $cuenta->monto - $pagado, 2);
        $cuenta->update([
            'saldo' => max(0, $saldo),
            'estado' => $cuenta->estado === EstadoCuenta::Anulada ? EstadoCuenta::Anulada : ($saldo <= 0 ? EstadoCuenta::Pagada : EstadoCuenta::Pendiente),
        ]);
    }
}
