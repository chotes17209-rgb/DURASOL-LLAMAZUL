<x-layouts.app title="Precios de compra en planta" breadcrumb="Precios">
    <x-slot:actions>
        <a href="{{ route('precios.compra.historial') }}" class="btn btn-secondary"><x-heroicon-o-clock/> Historial de cambios</a>
    </x-slot:actions>
    <x-remote-table :url="route('precios.compra.index')">
        <x-slot:filters>
            <x-search placeholder="Código, responsable o placa..."/>
            <x-field.select name="empresa_id" :options="$empresas" placeholder="Todas las empresas" class="w-52" :selected="request('empresa_id')"/>
            <x-field.select name="estado" :options="['pendiente' => 'Solo no validados']" placeholder="Todas" class="w-44" :selected="request('estado')"/>
        </x-slot:filters>
        @include('precios.compra._tabla')
    </x-remote-table>
</x-layouts.app>
