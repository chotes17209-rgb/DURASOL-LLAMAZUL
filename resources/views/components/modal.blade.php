@props(['title', 'subtitle' => null, 'icon' => null, 'color' => null])
{{-- Contenido estándar de un modal: cabecera institucional, cuerpo con desplazamiento y pie. --}}
<div class="flex max-h-[calc(100vh-5rem)] flex-col">
    <div class="flex items-start justify-between gap-4 bg-brand-900 px-5 py-3 text-white">
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-[#b9c7df]">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" data-modal-close class="-mr-2 inline-flex h-7 w-7 items-center justify-center rounded text-[#b9c7df] hover:bg-white/10 hover:text-white" title="Cerrar (Esc)"><x-heroicon-o-x-mark class="h-4 w-4"/></button>
    </div>
    <div class="h-[3px] bg-accent"></div>
    <div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto px-5 py-4']) }}>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-panel px-5 py-3">
            {{ $footer }}
        </div>
    @endisset
</div>
