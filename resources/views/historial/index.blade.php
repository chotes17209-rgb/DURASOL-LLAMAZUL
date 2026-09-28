<x-layouts.app title="Historial del sistema" breadcrumb="Administración">
    <div class="mb-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200">
        Registro de <b>todo</b> lo que se hace: quién creó, modificó o eliminó cada registro, con los valores antes y después, inicios de sesión y cierres de liquidación.
    </div>
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
