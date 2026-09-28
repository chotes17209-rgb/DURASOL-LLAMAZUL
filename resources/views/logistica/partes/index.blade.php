<x-layouts.app title="Partes diarios" breadcrumb="Logística">
    <x-slot:actions>
        <form method="GET" action="{{ route('logistica.partes.abrir') }}" class="flex items-center gap-2">
            <input type="date" name="fecha" value="{{ today()->toDateString() }}" class="form-input w-36">
            <button class="btn btn-primary">Abrir parte</button>
        </form>
    </x-slot:actions>
    <x-remote-table :url="route('logistica.partes.index')">
        <x-slot:filters>
            <div><label class="form-label">Mes</label><input type="month" name="mes" value="{{ request('mes') }}" class="form-input w-40"></div>
        </x-slot:filters>
        @include('logistica.partes._table')
    </x-remote-table>
</x-layouts.app>
