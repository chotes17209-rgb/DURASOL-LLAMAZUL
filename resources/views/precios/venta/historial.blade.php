<x-layouts.app title="Historial de precios de venta" breadcrumb="Precios">
    <x-slot:actions>
        <a href="{{ route('precios.venta.index') }}" class="btn btn-secondary"><x-heroicon-o-arrow-left class="h-4 w-4"/> Precios de venta</a>
    </x-slot:actions>
    <x-remote-table :url="route('precios.venta.historial')">
        <x-slot:filters>
            <x-search placeholder="Buscar cliente..."/>
            <x-field.select name="producto_id" :options="$productos" placeholder="Todos los productos" class="w-52" :selected="request('producto_id')"/>
            <x-field.input name="desde" type="date" :value="request('desde')" class="w-40"/>
            <x-field.input name="hasta" type="date" :value="request('hasta')" class="w-40"/>
        </x-slot:filters>
        @include('precios.venta._historial')
    </x-remote-table>
</x-layouts.app>
