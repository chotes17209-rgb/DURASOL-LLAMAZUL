<?php

namespace App\Enums;

/**
 * Estado de una salida de balones a un chofer.
 */
enum EstadoDespacho: string
{
    use EnumHelpers;

    case EnRuta = 'en_ruta';
    case Retornado = 'retornado';
    case Anulado = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::EnRuta => 'En ruta',
            self::Retornado => 'Retornado',
            self::Anulado => 'Anulado',
        };
    }
}
