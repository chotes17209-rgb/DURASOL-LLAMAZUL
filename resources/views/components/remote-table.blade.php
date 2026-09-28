@props(['url', 'sync' => true])
{{-- Tabla que se recarga por AJAX al filtrar, buscar o paginar. --}}
<div class="card" data-remote-table data-url="{{ $url }}" @if($sync) data-sync-url @endif>
    @isset($filters)
        <form data-table-filters class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4" onsubmit="return false">
            {{ $filters }}
        </form>
    @endisset
    <div data-table-body>
        {{ $slot }}
    </div>
</div>
