<?php

namespace App\Enums;

/**
 * Roles de usuario del sistema.
 */
enum Rol: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Logistica = 'logistica';
    case Caja = 'caja';
    case Liquidaciones = 'liquidaciones';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador / Gerencia',
            self::Logistica => 'Área logística',
            self::Caja => 'Caja',
            self::Liquidaciones => 'Liquidaciones y ventas',
        };
    }
}
