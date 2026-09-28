<x-layouts.app title="Choferes" breadcrumb="Flota y personal">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('choferes.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nuevo chofer</button>
    </x-slot:actions>
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-4">
        <x-cifra label="Choferes activos" :value="num($resumen['activos'])"/>
        <x-cifra label="Reparto local" :value="num($resumen['locales'])" hint="liquidan al día siguiente"/>
        <x-cifra label="Ruta (otras ciudades)" :value="num($resumen['ruta'])" hint="liquidan al volver"/>
        <x-cifra label="Clientes asignados" :value="num($resumen['clientes'])" total/>
    </dl>
    <x-remote-table :url="route('choferes.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar por nombre o DNI..."/>
            <x-field.select name="tipo" :options="\App\Enums\TipoChofer::options()" placeholder="Todos los tipos" class="w-60" :selected="request('tipo')"/>
            <x-field.select name="activo" :options="['1' => 'Activos', '0' => 'Inactivos']" placeholder="Todos" class="w-40" :selected="request('activo')"/>
        </x-slot:filters>
        @include('flota.choferes._table')
    </x-remote-table>
</x-layouts.app>
