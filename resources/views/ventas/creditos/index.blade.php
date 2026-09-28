<x-layouts.app title="Créditos y cobranzas" breadcrumb="Ventas">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('creditos.cobranzas.create') }}" data-modal-size="md"><x-heroicon-o-banknotes class="h-4 w-4"/> Registrar cobranza</button>
    </x-slot:actions>
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-4">
        <x-cifra label="Clientes con deuda" :value="num($clientesConDeuda)" hint="con saldo pendiente"/>
        <x-cifra label="Deuda de más de 30 días" :value="soles($vencidas30)" tone="red" hint="requiere seguimiento"/>
        <x-cifra label="Cobrado este mes" :value="soles($cobradoMes)" tone="green" hint="cobranzas registradas"/>
        <x-cifra label="Total por cobrar" :value="soles($totalPendiente)" hint="créditos pendientes" total/>
    </dl>
    <x-remote-table :url="route('creditos.index')">
        <x-slot:filters>
            <x-field.select name="vista" :options="['clientes' => 'Agrupado por cliente', 'detalle' => 'Detalle de créditos']" :selected="$vista" :empty="false" class="w-56"/>
            <x-search placeholder="Buscar cliente..."/>
            <x-field.select name="chofer_id" :options="$choferes" placeholder="Todos los choferes" class="w-48" :selected="request('chofer_id')"/>
        </x-slot:filters>
        @include('ventas.creditos._table')
    </x-remote-table>
</x-layouts.app>
