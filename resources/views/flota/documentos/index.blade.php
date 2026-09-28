<x-layouts.app title="Documentos vehiculares" breadcrumb="Flota y personal">
    <x-slot:actions>
        <a href="{{ route('vehiculos.index') }}" class="btn btn-secondary"><x-heroicon-o-arrow-left class="h-4 w-4"/> Vehículos</a>
    </x-slot:actions>
    <x-remote-table :url="route('documentos.index')">
        <x-slot:filters>
            <x-field.select name="vehiculo_id" :options="$vehiculos" placeholder="Todos los vehículos" class="w-52" :selected="request('vehiculo_id')"/>
            <x-field.select name="tipo" :options="\App\Enums\TipoDocumentoVehicular::options()" placeholder="Todos los documentos" class="w-60" :selected="request('tipo')"/>
            <x-field.select name="alerta" :options="['vencidos' => 'Vencidos', 'por_vencer' => 'Por vencer (30 días)']" placeholder="Todos" class="w-52" :selected="request('alerta')"/>
        </x-slot:filters>
        @include('flota.documentos._table')
    </x-remote-table>
</x-layouts.app>
