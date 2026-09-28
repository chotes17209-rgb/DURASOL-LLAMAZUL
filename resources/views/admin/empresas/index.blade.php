<x-layouts.app title="Empresas" breadcrumb="Administración">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('empresas.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nueva empresa</button>
    </x-slot:actions>
    <x-remote-table :url="route('empresas.index')">
        <x-slot:filters><x-search placeholder="Buscar por nombre o RUC..."/></x-slot:filters>
        @include('admin.empresas._table')
    </x-remote-table>
</x-layouts.app>
