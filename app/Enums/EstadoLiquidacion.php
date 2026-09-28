<?php

namespace App\Enums;

/**
 * Estado de una liquidación.
 */
enum EstadoLiquidacion: string
{
    use EnumHelpers;

    case Borrador = 'borrador';
    case Cerrada = 'cerrada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Cerrada => 'Cerrada',
            self::Anulada => 'Anulada',
        };
    }
}
