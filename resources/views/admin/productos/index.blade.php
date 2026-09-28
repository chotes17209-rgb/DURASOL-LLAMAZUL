<x-layouts.app title="Productos" breadcrumb="Administración">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('productos.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nuevo producto</button>
    </x-slot:actions>
    <p class="help mb-3">S10, S45 y M10: compra en planta · C10 y C45: Contigas · K10 y K45: venta de envase · REG: reguladores. La columna «Envase» indica el balón vacío que se recupera en cada venta.</p>
    <x-remote-table :url="route('productos.index')">
        <x-slot:filters><x-search placeholder="Buscar producto..."/></x-slot:filters>
        @include('admin.productos._table')
    </x-remote-table>
</x-layouts.app>
