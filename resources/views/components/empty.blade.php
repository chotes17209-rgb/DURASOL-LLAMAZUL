@props(['title' => 'Sin registros', 'text' => 'No hay información para mostrar con los filtros actuales.', 'icon' => null])
<div class="px-6 py-10 text-center">
    <p class="text-[13px] font-medium text-slate-600">{{ $title }}</p>
    <p class="mt-1 text-xs text-slate-400">{{ $text }}</p>
    {{ $slot }}
</div>
