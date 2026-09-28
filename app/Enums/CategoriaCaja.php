<?php

namespace App\Enums;

/**
 * Categorías de los movimientos de caja.
 */
enum CategoriaCaja: string
{
    use EnumHelpers;

    case Liquidacion = 'liquidacion';
    case Cobranza = 'cobranza';
    case Deposito = 'deposito';
    case Gasto = 'gasto';
    case CajaChica = 'caja_chica';
    case Planilla = 'planilla';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Liquidacion => 'Liquidación',
            self::Cobranza => 'Cobranza',
            self::Deposito => 'Depósito bancario',
            self::Gasto => 'Gasto',
            self::CajaChica => 'Caja chica',
            self::Planilla => 'Planilla',
            self::Otro => 'Otro',
        };
    }
}
