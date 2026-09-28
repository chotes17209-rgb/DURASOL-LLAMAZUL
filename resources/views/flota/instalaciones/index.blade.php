<x-layouts.app title="Instalaciones Solgas" breadcrumb="Flota y personal">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('instalaciones.create') }}" data-modal-size="lg"><x-heroicon-o-plus/> Nueva instalación</button>
    </x-slot:actions>
    <x-remote-table :url="route('instalaciones.index')">
        <x-slot:filters>
            <x-search placeholder="Código, responsable o placa..."/>
            <x-field.select name="empresa_id" :options="$empresas" placeholder="Todas las empresas" class="w-48" :selected="request('empresa_id')"/>
            <x-field.select name="estado" :options="['pendiente' => 'Solo no validados', 'inactivas' => 'Inactivas']" placeholder="Activas" class="w-44" :selected="request('estado')"/>
        </x-slot:filters>
        @include('flota.instalaciones._table')
    </x-remote-table>
</x-layouts.app>
