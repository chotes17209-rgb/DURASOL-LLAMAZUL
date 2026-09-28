<x-layouts.app title="Kardex de balones" breadcrumb="Logística · Movimiento de masa">
    <x-slot:actions>
        <a href="{{ route('logistica.stock') }}" class="btn btn-secondary"><x-heroicon-o-arrow-left class="h-4 w-4"/> Stock</a>
    </x-slot:actions>
    <x-remote-table :url="route('logistica.stock.kardex')">
        <x-slot:filters>
            <x-field.select name="producto_id" label="Producto" :options="$productos->pluck('codigo', 'id')" :selected="$productoId" :empty="false" class="w-36"/>
            <x-field.select name="estado" label="Estado" :options="\App\Enums\EstadoStock::options()" :selected="$estado" :empty="false" class="w-48"/>
            <x-field.select name="empresa_id" label="Empresa (llenos/cambios)" :options="$empresas->pluck('nombre', 'id')" :selected="$empresaId" :empty="false" class="w-52"/>
            <x-field.input name="desde" label="Desde" type="date" :value="$desde->format('Y-m-d')" class="w-40"/>
            <x-field.input name="hasta" label="Hasta" type="date" :value="$hasta->format('Y-m-d')" class="w-40"/>
        </x-slot:filters>
        @include('logistica.stock._kardex')
    </x-remote-table>
</x-layouts.app>
