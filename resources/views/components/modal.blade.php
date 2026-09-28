@props(['title', 'subtitle' => null, 'icon' => null, 'color' => null])
{{-- Contenido estándar de un modal: cabecera institucional, cuerpo con desplazamiento y pie. --}}
<div class="flex max-h-[calc(100vh-5rem)] flex-col">
    <div class="flex items-start justify-between gap-4 border-b border-line bg-panel px-5 py-3" style="box-shadow: inset 0 3px 0 var(--color-brand-800)">
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-slate-900">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" data-modal-close class="-mr-2 inline-flex h-7 w-7 items-center justify-center rounded-sm text-slate-500 hover:bg-[#e8ebef] hover:text-slate-900" title="Cerrar (Esc)"><x-heroicon-o-x-mark class="h-4 w-4"/></button>
    </div>
    <div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto px-5 py-4']) }}>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-panel px-5 py-2.5">
            {{ $footer }}
        </div>
    @endisset
</div>
