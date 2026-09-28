@props(['url'])
{{-- Botones de descarga PDF / Excel. Si la página tiene filtros, se envían con la descarga. --}}
<div class="flex">
    <a href="{{ $url }}" data-export="pdf" class="btn btn-secondary rounded-r-none" title="Descargar en PDF"><x-heroicon-o-document-arrow-down/> PDF</a>
    <a href="{{ $url }}" data-export="xlsx" class="btn btn-secondary -ml-px rounded-l-none" title="Descargar en Excel"><x-heroicon-o-table-cells/> Excel</a>
</div>
