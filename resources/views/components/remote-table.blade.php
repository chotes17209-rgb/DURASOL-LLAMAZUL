@props(['url', 'sync' => true, 'title' => null])
{{-- Tabla que se recarga por AJAX al filtrar, buscar o paginar. --}}
<div class="card" data-remote-table data-url="{{ $url }}" @if($sync) data-sync-url @endif>
    @if ($title)
        <div class="card-header"><p class="card-title">{{ $title }}</p>{{ $header ?? '' }}</div>
    @endif
    @isset($filters)
        <form data-table-filters class="flex flex-wrap items-end gap-2 border-b border-slate-100 px-4 py-3" onsubmit="return false">
            {{ $filters }}
            <button type="button" class="btn btn-secondary h-8" title="Quitar filtros" data-limpiar-filtros>Limpiar filtros</button>
        </form>
    @endisset
    <div data-table-body class="transition-opacity">
        {{ $slot }}
    </div>
</div>
