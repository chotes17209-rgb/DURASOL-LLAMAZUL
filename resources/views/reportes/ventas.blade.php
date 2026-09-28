<x-layouts.app title="Detalle de ventas" breadcrumb="Reportes">
    <x-slot:actions>
        <x-export :url="route('reportes.ventas.exportar')"/>
    </x-slot:actions>
    <x-remote-table :url="route('reportes.ventas')">
        <x-slot:filters>
            <x-field.input name="desde" label="Desde" type="date" :value="request('desde', today()->startOfMonth()->format('Y-m-d'))" class="w-40"/>
            <x-field.input name="hasta" label="Hasta" type="date" :value="request('hasta', today()->format('Y-m-d'))" class="w-40"/>
            <x-field.select name="chofer_id" label="Chofer" :options="$choferes" placeholder="Todos" class="w-40" :selected="request('chofer_id')"/>
            <x-field.select name="empresa_id" label="Empresa" :options="$empresas" placeholder="Todas" class="w-40" :selected="request('empresa_id')"/>
            <x-field.select name="producto_id" label="Producto" :options="$productos" placeholder="Todos" class="w-32" :selected="request('producto_id')"/>
            <x-field.select name="solo_credito" label="Pago" :options="['1' => 'Solo créditos']" placeholder="Todos" class="w-40" :selected="request('solo_credito')"/>
            <div class="min-w-56 flex-1"><label class="form-label">Cliente</label><input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Buscar cliente..."></div>
        </x-slot:filters>
        @include('reportes._ventas')
    </x-remote-table>
</x-layouts.app>
