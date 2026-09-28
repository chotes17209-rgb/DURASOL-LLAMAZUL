<?php

namespace App\Enums;

/**
 * Estado físico de un balón dentro del stock.
 */
enum EstadoStock: string
{
    use EnumHelpers;

    case Lleno = 'lleno';
    case Vacio = 'vacio';
    case Color = 'color';
    case Cambio = 'cambio';

    public function label(): string
    {
        return match ($this) {
            self::Lleno => 'Lleno',
            self::Vacio => 'Vacío (plomo)',
            self::Color => 'Vacío de color',
            self::Cambio => 'Cambio (fallado)',
        };
    }
}
