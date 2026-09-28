<?php

namespace App\Enums;

/**
 * Motivos de un movimiento manual de stock.
 */
enum TipoMovimientoManual: string
{
    use EnumHelpers;

    case StockInicial = 'stock_inicial';
    case IngresoVacios = 'ingreso_vacios';
    case Prestamo = 'prestamo';
    case Merma = 'merma';
    case Ajuste = 'ajuste';

    public function label(): string
    {
        return match ($this) {
            self::StockInicial => 'Stock inicial',
            self::IngresoVacios => 'Ingreso de vacíos',
            self::Prestamo => 'Préstamo',
            self::Merma => 'Merma / pérdida',
            self::Ajuste => 'Ajuste de inventario',
        };
    }
}
