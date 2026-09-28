@props(['label', 'value', 'icon' => 'chart-bar', 'color' => 'brand', 'hint' => null])
<div class="kpi">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="kpi-label">{{ $label }}</p>
            <p class="kpi-value">{{ $value }}</p>
            @if ($hint)<p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>@endif
        </div>
        <div @class([
            'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl',
            'bg-brand-50 text-brand-600' => $color === 'brand',
            'bg-emerald-50 text-emerald-600' => $color === 'green',
            'bg-rose-50 text-rose-600' => $color === 'red',
            'bg-amber-50 text-amber-600' => $color === 'amber',
            'bg-violet-50 text-violet-600' => $color === 'violet',
            'bg-orange-50 text-orange-600' => $color === 'orange',
            'bg-sky-50 text-sky-600' => $color === 'sky',
        ])>
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5"/>
        </div>
    </div>
    {{ $slot }}
</div>
