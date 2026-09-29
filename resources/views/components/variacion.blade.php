@props(['actual', 'anterior', 'texto'])
{{-- Variación porcentual frente a un periodo anterior: ▲ verde si sube, ▼ rojo si baja. --}}
@php
    $actual = (float) $actual;
    $anterior = (float) $anterior;
    $pct = $anterior > 0 ? ($actual - $anterior) / $anterior * 100 : null;
@endphp
<span {{ $attributes->merge(['class' => 'variacion']) }} title="Anterior: {{ number_format($anterior, 2) }}">
    @if ($pct === null)
        <span class="text-slate-400">— {{ $texto }}</span>
    @else
        <span class="{{ $pct >= 0 ? 'text-emerald-700' : 'text-red-700' }} font-semibold">{{ $pct >= 0 ? '▲' : '▼' }} {{ number_format(abs($pct), 1) }} %</span>
        <span class="text-slate-500">{{ $texto }}</span>
    @endif
</span>
