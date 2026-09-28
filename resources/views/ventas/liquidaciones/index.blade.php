<x-layouts.app title="Liquidaciones" breadcrumb="Ventas">
    <x-slot:actions>
        <a href="{{ route('reportes.liquidacion-diaria') }}" class="btn btn-secondary"><x-heroicon-o-document-chart-bar class="h-4 w-4"/> Hoja diaria</a>
        <a href="{{ route('liquidaciones.create') }}" class="btn btn-primary"><x-heroicon-o-plus class="h-4 w-4"/> Nueva liquidación</a>
    </x-slot:actions>
    @if ($borradores)
        <div class="help mb-4 flex items-center justify-between border-amber-300 bg-amber-50 text-amber-900">
            <span>Hay <b>{{ $borradores }}</b> liquidación(es) en borrador pendientes de cerrar en caja.</span>
            <a href="{{ route('liquidaciones.index', ['estado' => 'borrador']) }}" class="font-semibold text-brand-800 hover:underline">Ver borradores</a>
        </div>
    @endif
    <x-remote-table :url="route('liquidaciones.index')">
        <x-slot:filters>
            <x-search placeholder="Código LIQ-..."/>
            <x-field.select name="chofer_id" :options="$choferes" placeholder="Todos los choferes" class="w-52" :selected="request('chofer_id')"/>
            <x-field.select name="estado" :options="\App\Enums\EstadoLiquidacion::options()" placeholder="Todos los estados" class="w-52" :selected="request('estado')"/>
            <x-field.input name="desde" type="date" :value="request('desde')" class="w-40"/>
            <x-field.input name="hasta" type="date" :value="request('hasta')" class="w-40"/>
        </x-slot:filters>
        @include('ventas.liquidaciones._table')
    </x-remote-table>
</x-layouts.app>
