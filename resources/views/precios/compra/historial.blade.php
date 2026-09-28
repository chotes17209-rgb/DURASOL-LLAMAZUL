<x-layouts.app title="Historial de precios de compra" breadcrumb="Precios">
    <x-slot:actions>
        <a href="{{ route('precios.compra.index') }}" class="btn btn-secondary"><x-heroicon-o-arrow-left class="h-4 w-4"/> Precios de compra</a>
    </x-slot:actions>
    <x-remote-table :url="route('precios.compra.historial')">
        <x-slot:filters>
            <x-field.select name="empresa_id" :options="$empresas" placeholder="Todas las empresas" class="w-52" :selected="request('empresa_id')"/>
            <x-field.select name="instalacion_id" :options="$instalaciones" placeholder="Todas las instalaciones" class="w-64" :selected="request('instalacion_id')"/>
            <x-field.select name="producto_id" :options="$productos" placeholder="Todos los productos" class="w-52" :selected="request('producto_id')"/>
        </x-slot:filters>
        @include('precios.compra._historial')
    </x-remote-table>
</x-layouts.app>
