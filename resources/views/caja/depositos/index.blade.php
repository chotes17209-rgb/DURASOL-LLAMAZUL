<x-layouts.app title="Depósitos bancarios" breadcrumb="Caja">
    <x-slot:actions>
        <a href="{{ route('cuentas-bancarias.index') }}" class="btn btn-secondary"><x-heroicon-o-building-library class="h-4 w-4"/> Cuentas</a>
        <button class="btn btn-primary" data-modal-url="{{ route('caja.depositos.create') }}" data-con-fecha data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nuevo depósito</button>
    </x-slot:actions>
    @include('caja._tabs')
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-4">
        <x-cifra label="Mes" :value="ucfirst(today()->translatedFormat('F Y'))"/>
        <x-cifra label="Depositado este mes" :value="soles($totalMes)" total/>
    </dl>
    <x-remote-table :url="route('caja.depositos.index')">
        <x-slot:filters>
            <x-search placeholder="Depositante u operación..."/>
            <x-field.select name="cuenta_bancaria_id" :options="$cuentas" placeholder="Todas las cuentas" class="w-56" :selected="request('cuenta_bancaria_id')"/>
            <x-field.input name="desde" type="date" :value="request('desde')" class="w-40"/>
            <x-field.input name="hasta" type="date" :value="request('hasta')" class="w-40"/>
        </x-slot:filters>
        @include('caja.depositos._table')
    </x-remote-table>
</x-layouts.app>
