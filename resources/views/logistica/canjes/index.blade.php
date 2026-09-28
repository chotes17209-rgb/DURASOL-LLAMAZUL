<x-layouts.app title="Canjes de balones de color" breadcrumb="Logística · Movimiento de masa">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('logistica.canjes.create') }}" data-modal-size="md"><x-heroicon-o-arrows-right-left class="h-4 w-4"/> Nuevo canje</button>
    </x-slot:actions>
    <div class="mb-4 rounded-2xl bg-violet-50 p-4 text-sm text-violet-900 ring-1 ring-violet-100">
        Los balones <b>de color</b> son de otras marcas. Se entregan a otra empresa o móvil a cambio de balones <b>plomo</b> de Solgas, que sí se pueden llevar a planta.
    </div>
    <x-remote-table :url="route('logistica.canjes.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar contraparte..."/>
            <x-field.input name="desde" type="date" :value="request('desde')" class="w-40"/>
            <x-field.input name="hasta" type="date" :value="request('hasta')" class="w-40"/>
        </x-slot:filters>
        @include('logistica.canjes._table')
    </x-remote-table>
</x-layouts.app>
