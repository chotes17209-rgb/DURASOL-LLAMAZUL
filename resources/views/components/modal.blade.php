@props(['title', 'subtitle' => null, 'icon' => null, 'color' => null])
{{-- Contenido estándar de un modal: cabecera institucional, cuerpo con desplazamiento y pie. --}}
<div class="flex max-h-[calc(100vh-5rem)] flex-col">
    <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-5 py-3.5">
        <div class="min-w-0">
            <h2 class="text-[16px] font-semibold tracking-tight text-slate-900">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" data-modal-close class="-mr-2 inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-[#e8ebef] hover:text-slate-900" title="Cerrar (Esc)"><x-heroicon-o-x-mark class="h-4 w-4"/></button>
    </div>
    <div {{ $attributes->merge(['class' => 'flex-1 overflow-y-auto px-5 py-4']) }}>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3">
            {{ $footer }}
        </div>
    @endisset
</div>
