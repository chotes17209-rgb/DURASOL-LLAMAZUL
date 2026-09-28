<x-layouts.app title="Historial del sistema" breadcrumb="Administración">
    <p class="help mb-4">Registro de auditoría: creación, modificación y eliminación de registros (valores anteriores y nuevos), inicios de sesión, cierres de liquidación y descargas de reportes.</p>
    <x-remote-table :url="route('historial.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar en los datos..."/>
            <x-field.select name="user_id" :options="$usuarios" placeholder="Todos los usuarios" class="w-48" :selected="request('user_id')"/>
            <x-field.select name="modulo" :options="$modulos" placeholder="Todos los módulos" class="w-52" :selected="request('modulo')"/>
            <x-field.select name="evento" :options="$eventos" placeholder="Todas las acciones" class="w-48" :selected="request('evento')"/>
            <x-field.input name="desde" type="date" :value="request('desde')" class="w-40"/>
            <x-field.input name="hasta" type="date" :value="request('hasta')" class="w-40"/>
        </x-slot:filters>
        @include('historial._table')
    </x-remote-table>
</x-layouts.app>
