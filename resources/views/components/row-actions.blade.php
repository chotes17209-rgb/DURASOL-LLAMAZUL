@props(['show' => null, 'edit' => null, 'delete' => null, 'size' => 'lg', 'deleteText' => null])
{{-- Botones Ver / Editar / Eliminar de cada fila. --}}
<div class="flex items-center justify-end gap-1">
    {{ $slot }}
    @if ($show)
        <button type="button" class="btn-icon info" title="Ver detalle" data-modal-url="{{ $show }}" data-modal-size="{{ $size }}"><x-heroicon-o-eye class="h-4 w-4"/></button>
    @endif
    @if ($edit)
        <button type="button" class="btn-icon info" title="Editar" data-modal-url="{{ $edit }}" data-modal-size="{{ $size }}"><x-heroicon-o-pencil-square class="h-4 w-4"/></button>
    @endif
    @if ($delete)
        <button type="button" class="btn-icon danger" title="Eliminar" data-delete-url="{{ $delete }}" @if($deleteText) data-text="{{ $deleteText }}" @endif><x-heroicon-o-trash class="h-4 w-4"/></button>
    @endif
</div>
