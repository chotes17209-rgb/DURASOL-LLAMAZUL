<x-layouts.app title="Vehículos" breadcrumb="Flota y personal">
    <x-slot:actions>
        <a href="{{ route('documentos.index') }}" class="btn btn-secondary"><x-heroicon-o-document-check class="h-4 w-4"/> Documentos</a>
        <button class="btn btn-primary" data-modal-url="{{ route('vehiculos.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nuevo vehículo</button>
    </x-slot:actions>
    <x-remote-table :url="route('vehiculos.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar placa, marca o modelo..."/>
            <x-field.select name="estado" :options="\App\Models\Vehiculo::ESTADOS" placeholder="Todos los estados" class="w-52" :selected="request('estado')"/>
        </x-slot:filters>
        @include('flota.vehiculos._table')
    </x-remote-table>
</x-layouts.app>
