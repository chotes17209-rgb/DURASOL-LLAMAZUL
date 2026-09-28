<?php

namespace App\Enums;

/**
 * Estado de una guía de abastecimiento en planta.
 */
enum EstadoGuia: string
{
    use EnumHelpers;

    case EnTransito = 'en_transito';
    case Recibida = 'recibida';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::EnTransito => 'En tránsito',
            self::Recibida => 'Recibida',
            self::Anulada => 'Anulada',
        };
    }
}
