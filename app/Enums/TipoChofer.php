<?php

namespace App\Enums;

/**
 * Cómo trabaja cada chofer.
 */
enum TipoChofer: string
{
    use EnumHelpers;

    case Local = 'local';
    case Ruta = 'ruta';
    case Planta = 'planta';
    case Almacen = 'almacen';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Reparto local',
            self::Ruta => 'Ruta (otras ciudades)',
            self::Planta => 'Abastecimiento en planta',
            self::Almacen => 'Venta en almacén / local',
            self::Otro => 'Otro',
        };
    }
}
