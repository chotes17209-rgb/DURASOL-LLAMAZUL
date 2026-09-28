<x-layouts.app title="Créditos y cobranzas" breadcrumb="Ventas">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('creditos.cobranzas.create') }}" data-modal-size="md"><x-heroicon-o-banknotes class="h-4 w-4"/> Registrar cobranza</button>
    </x-slot:actions>
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Por cobrar" :value="soles($totalPendiente)" icon="credit-card" color="red"/>
        <x-kpi label="Clientes con deuda" :value="num($clientesConDeuda)" icon="users" color="amber"/>
        <x-kpi label="Deuda de más de 30 días" :value="soles($vencidas30)" icon="clock" color="orange"/>
        <x-kpi label="Cobrado este mes" :value="soles($cobradoMes)" icon="check-badge" color="green"/>
    </div>
    <x-remote-table :url="route('creditos.index')">
        <x-slot:filters>
            <x-field.select name="vista" :options="['clientes' => 'Agrupado por cliente', 'detalle' => 'Detalle de créditos']" :selected="$vista" :empty="false" class="w-56"/>
            <x-search placeholder="Buscar cliente..."/>
            <x-field.select name="chofer_id" :options="$choferes" placeholder="Todos los choferes" class="w-48" :selected="request('chofer_id')"/>
        </x-slot:filters>
        @include('ventas.creditos._table')
    </x-remote-table>
</x-layouts.app>
