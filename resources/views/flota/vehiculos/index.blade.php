<x-layouts.app title="Vehículos" breadcrumb="Flota y personal">
    <x-slot:actions>
        <a href="{{ route('documentos.index') }}" class="btn btn-secondary"><x-heroicon-o-document-check class="h-4 w-4"/> Documentos</a>
        <button class="btn btn-primary" data-modal-url="{{ route('vehiculos.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nuevo vehículo</button>
    </x-slot:actions>
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-5">
        <x-cifra label="Vehículos" :value="num($resumen['total'])" :hint="num($resumen['operativos']).' operativos'"/>
        <x-cifra label="Documentos vencidos" :value="num($resumen['vencidos'])" tone="red" hint="SOAT, revisión técnica, DGH"/>
        <x-cifra label="Por vencer (30 días)" :value="num($resumen['porVencer'])" hint="renovar pronto"/>
        <x-cifra label="Sin registrar" :value="num($resumen['sinRegistro'])" hint="documentos por cargar"/>
        <x-cifra label="Al día" :value="num($resumen['vigentes'])" hint="documentos vigentes" total/>
    </dl>
    <x-remote-table :url="route('vehiculos.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar placa, marca o modelo..."/>
            <x-field.select name="estado" :options="\App\Models\Vehiculo::ESTADOS" placeholder="Todos los estados" class="w-52" :selected="request('estado')"/>
        </x-slot:filters>
        @include('flota.vehiculos._table')
    </x-remote-table>
</x-layouts.app>
