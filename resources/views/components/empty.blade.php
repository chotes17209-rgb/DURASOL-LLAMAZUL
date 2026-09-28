@props(['title' => 'Sin registros', 'text' => 'No hay información para mostrar con los filtros actuales.', 'icon' => 'inbox'])
<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-7 w-7"/>
    </div>
    <p class="mt-4 text-sm font-semibold text-slate-700">{{ $title }}</p>
    <p class="mt-1 max-w-sm text-sm text-slate-400">{{ $text }}</p>
    {{ $slot }}
</div>
