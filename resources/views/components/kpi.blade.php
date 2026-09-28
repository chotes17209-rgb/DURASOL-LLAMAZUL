@props(['label', 'value', 'icon' => null, 'color' => 'brand', 'hint' => null])
@php($borde = ['brand' => 'border-l-brand-700', 'green' => 'border-l-emerald-600', 'red' => 'border-l-red-600', 'amber' => 'border-l-amber-500', 'orange' => 'border-l-orange-500', 'violet' => 'border-l-violet-600', 'sky' => 'border-l-sky-500', 'slate' => 'border-l-slate-400'][$color] ?? 'border-l-brand-700')
<div class="kpi border-l-[3px] {{ $borde }}">
    <p class="kpi-label">{{ $label }}</p>
    <p class="kpi-value">{{ $value }}</p>
    @if ($hint)<p class="mt-0.5 text-[12px] text-slate-500">{{ $hint }}</p>@endif
    {{ $slot }}
</div>
