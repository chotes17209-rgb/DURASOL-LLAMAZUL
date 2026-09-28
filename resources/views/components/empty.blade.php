@props(['title' => 'Sin registros', 'text' => 'No hay información para mostrar con los filtros actuales.', 'icon' => 'inbox'])
<div class="flex flex-col items-center px-6 py-12 text-center">
    <span class="flex h-12 w-12 items-center justify-center rounded-full border border-line bg-panel text-slate-400">
        <x-dynamic-component :component="'heroicon-o-'.($icon ?: 'inbox')" class="h-6 w-6"/>
    </span>
    <p class="mt-3 text-[14px] font-semibold text-brand-950">{{ $title }}</p>
    <p class="mt-1 max-w-sm text-xs text-slate-500">{{ $text }}</p>
    @if (trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>
