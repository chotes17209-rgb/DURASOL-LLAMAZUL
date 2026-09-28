<x-layouts.app title="Clientes" breadcrumb="Ventas">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('clientes.create') }}" data-modal-size="lg"><x-heroicon-o-user-plus class="h-4 w-4"/> Nuevo cliente</button>
    </x-slot:actions>
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-4">
        <x-cifra label="Clientes activos" :value="num($resumen['activos'])" :hint="num($resumen['locales']).' de reparto local'"/>
        <x-cifra label="Rutas y otros" :value="num($resumen['activos'] - $resumen['locales'])" hint="provincias, mina, instituciones"/>
        <x-cifra label="Con crédito pendiente" :value="num($resumen['conDeuda'])" tone="red" hint="clientes con saldo por cobrar"/>
        <x-cifra label="Deuda total de clientes" :value="soles($resumen['deuda'])" total/>
    </dl>
    <x-remote-table :url="route('clientes.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar por código, nombre, conocido como o dirección..."/>
            <x-field.select name="chofer_id" :options="$choferes" placeholder="Todos los choferes" class="w-52" :selected="request('chofer_id')"/>
            <x-field.select name="tipo" :options="\App\Models\Cliente::TIPOS" placeholder="Todos los tipos" class="w-44" :selected="request('tipo')"/>
            <x-field.select name="activo" :options="['1' => 'Activos', '0' => 'Inactivos']" placeholder="Todos" class="w-36" :selected="request('activo')"/>
        </x-slot:filters>
        @include('ventas.clientes._table')
    </x-remote-table>
</x-layouts.app>
