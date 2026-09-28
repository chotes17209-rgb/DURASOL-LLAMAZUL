@props(['url', 'sync' => true, 'title' => null])
{{-- Tabla que se recarga por AJAX al filtrar, buscar o paginar. --}}
<div class="card" data-remote-table data-url="{{ $url }}" @if($sync) data-sync-url @endif>
    @if ($title)
        <div class="card-header"><p class="card-title">{{ $title }}</p>{{ $header ?? '' }}</div>
    @endif
    @isset($filters)
        <form data-table-filters class="flex flex-wrap items-end gap-2 border-b border-line bg-panel px-3 py-2.5" onsubmit="return false">
            <span class="hidden h-8 items-center gap-1.5 pr-1 text-[10.5px] font-semibold tracking-[.12em] text-brand-800 uppercase xl:flex"><x-heroicon-o-funnel class="h-4 w-4"/> Filtros</span>
            {{ $filters }}
            <button type="button" class="btn btn-ghost btn-sm h-8 text-slate-500" title="Quitar filtros" data-limpiar-filtros><x-heroicon-o-x-mark/> Limpiar</button>
        </form>
    @endisset
    <div data-table-body class="transition-opacity">
        {{ $slot }}
    </div>
</div>
