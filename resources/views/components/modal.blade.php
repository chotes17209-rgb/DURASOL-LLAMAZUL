@props(['title', 'subtitle' => null, 'icon' => null, 'color' => null])
{{-- Contenido estándar de un modal: cabecera, cuerpo con desplazamiento y pie. --}}
<div class="flex max-h-[calc(100vh-5rem)] flex-col">
    <div class="flex items-start justify-between gap-4 border-b border-line bg-panel px-5 py-3">
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-slate-900">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" data-modal-close class="btn-icon -mr-2" title="Cerrar (Esc)"><x-heroicon-o-x-mark/></button>
    </div>
    <div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto px-5 py-4']) }}>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-panel px-5 py-3">
            {{ $footer }}
        </div>
    @endisset
</div>
