@props(['value'])
{{-- Etiqueta de estado con color según el valor del enum. --}}
@php
    $v = $value instanceof \BackedEnum ? $value->value : $value;
    $label = $value instanceof \BackedEnum && method_exists($value, 'label') ? $value->label() : ucfirst(str_replace('_', ' ', (string) $v));
    $color = match ($v) {
        'cerrada', 'recibida', 'retornado', 'pagada', 'vigente', 'operativo' => 'green',
        'borrador', 'en_transito', 'en_ruta', 'pendiente', 'por_vencer', 'mantenimiento' => 'amber',
        'anulada', 'anulado', 'vencido', 'inactivo' => 'red',
        'sin_registro', 'sin_fecha' => 'slate',
        default => 'blue',
    };
    $label = match ($v) { 'por_vencer' => 'Por vencer', 'vencido' => 'Vencido', 'vigente' => 'Vigente', 'sin_registro' => 'Sin registro', 'sin_fecha' => 'Sin fecha', default => $label };
@endphp
<x-badge :color="$color">{{ $label }}</x-badge>
