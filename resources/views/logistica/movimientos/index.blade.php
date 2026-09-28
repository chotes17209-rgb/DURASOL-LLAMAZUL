<x-layouts.app title="Movimientos manuales de stock" breadcrumb="Logística · Movimiento de masa">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('logistica.movimientos.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nuevo movimiento</button>
    </x-slot:actions>
    <div class="mb-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200">
        Úsalo para el <b>stock inicial</b>, los <b>vacíos que dejan los clientes</b> en el local (Ejército, PNP, etc.), préstamos, mermas o ajustes de inventario.
        Las guías, despachos y canjes ya mueven el stock solos.
    </div>
    <x-remote-table :url="route('logistica.movimientos.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar referencia..."/>
            <x-field.select name="tipo" :options="\App\Enums\TipoMovimientoManual::options()" placeholder="Todos los tipos" class="w-52" :selected="request('tipo')"/>
            <x-field.input name="desde" type="date" :value="request('desde')" class="w-40"/>
            <x-field.input name="hasta" type="date" :value="request('hasta')" class="w-40"/>
        </x-slot:filters>
        @include('logistica.movimientos._table')
    </x-remote-table>
</x-layouts.app>
