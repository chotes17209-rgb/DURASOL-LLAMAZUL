<x-layouts.app title="Productos" breadcrumb="Administración">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('productos.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nuevo producto</button>
    </x-slot:actions>
    <div class="mb-4 rounded-2xl bg-brand-50 p-4 text-sm text-brand-900 ring-1 ring-brand-100">
        <b>¿Cómo se usan los productos?</b> S10, S45 y M10 se compran en planta. C10/C45 son Contigas. K10/K45 representan la venta de un balón vacío (envase)
        y REG los reguladores. El <b>envase</b> indica qué balón vacío queda al vender el producto (M10 y C10 usan el envase de 10&nbsp;kg).
    </div>
    <x-remote-table :url="route('productos.index')">
        <x-slot:filters><x-search placeholder="Buscar producto..."/></x-slot:filters>
        @include('admin.productos._table')
    </x-remote-table>
</x-layouts.app>
