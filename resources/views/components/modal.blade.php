@props(['title', 'subtitle' => null, 'icon' => 'document-text', 'color' => 'brand'])
{{-- Contenido estándar de un modal: cabecera, cuerpo con scroll y pie. --}}
<div class="flex max-h-[calc(100vh-4rem)] flex-col">
    <div class="flex items-start gap-4 border-b border-slate-100 px-6 py-5">
        <div @class([
            'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl',
            'bg-brand-50 text-brand-600' => $color === 'brand',
            'bg-emerald-50 text-emerald-600' => $color === 'green',
            'bg-rose-50 text-rose-600' => $color === 'red',
            'bg-amber-50 text-amber-600' => $color === 'amber',
            'bg-violet-50 text-violet-600' => $color === 'violet',
        ])>
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5"/>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-bold text-slate-900">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" data-modal-close class="btn-icon -mr-2" title="Cerrar"><x-heroicon-o-x-mark class="h-5 w-5"/></button>
    </div>
    <div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto px-6 py-5']) }}>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="flex flex-wrap items-center justify-end gap-2 rounded-b-3xl border-t border-slate-100 bg-slate-50/60 px-6 py-4">
            {{ $footer }}
        </div>
    @endisset
</div>
