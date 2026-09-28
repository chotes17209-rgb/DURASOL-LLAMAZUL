<?php

use Illuminate\Support\Carbon;

if (! function_exists('soles')) {
    /** Formatea un monto en soles: S/ 1,234.50 */
    function soles(float|int|string|null $monto, bool $simbolo = true): string
    {
        $texto = number_format((float) $monto, 2, '.', ',');

        return $simbolo ? 'S/ '.$texto : $texto;
    }
}

if (! function_exists('num')) {
    function num(float|int|string|null $valor, int $decimales = 0): string
    {
        return number_format((float) $valor, $decimales, '.', ',');
    }
}

if (! function_exists('fecha')) {
    function fecha(Carbon|string|null $fecha, string $formato = 'd/m/Y'): string
    {
        return $fecha ? Carbon::parse($fecha)->format($formato) : '';
    }
}
