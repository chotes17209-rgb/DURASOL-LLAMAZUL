<?php

namespace App\Enums;

/**
 * Formas de pago en liquidaciones y cobranzas.
 */
enum MetodoPago: string
{
    use EnumHelpers;

    case Efectivo = 'efectivo';
    case Yape = 'yape';
    case Plin = 'plin';
    case Transferencia = 'transferencia';
    case Deposito = 'deposito';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Yape => 'Yape',
            self::Plin => 'Plin',
            self::Transferencia => 'Transferencia',
            self::Deposito => 'Depósito',
        };
    }
}
