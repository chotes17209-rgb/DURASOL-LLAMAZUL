<x-layouts.app title="Cuentas bancarias" breadcrumb="Administración">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('cuentas-bancarias.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nueva cuenta</button>
    </x-slot:actions>
    <x-remote-table :url="route('cuentas-bancarias.index')">
        <x-slot:filters><x-search placeholder="Banco o alias..."/></x-slot:filters>
        @include('caja.cuentas._table')
    </x-remote-table>
</x-layouts.app>
