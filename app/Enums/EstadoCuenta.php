<?php

namespace App\Enums;

/**
 * Estado de una cuenta por cobrar.
 */
enum EstadoCuenta: string
{
    use EnumHelpers;

    case Pendiente = 'pendiente';
    case Pagada = 'pagada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Pagada => 'Pagada',
            self::Anulada => 'Anulada',
        };
    }
}
