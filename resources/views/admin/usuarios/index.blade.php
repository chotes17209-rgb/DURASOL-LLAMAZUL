<x-layouts.app title="Usuarios" breadcrumb="Administración">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('usuarios.create') }}" data-modal-size="md"><x-heroicon-o-user-plus class="h-4 w-4"/> Nuevo usuario</button>
    </x-slot:actions>
    <x-remote-table :url="route('usuarios.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar por nombre o usuario..."/>
            <x-field.select name="role" :options="\App\Enums\Rol::options()" placeholder="Todos los roles" class="w-56" :selected="request('role')"/>
        </x-slot:filters>
        @include('admin.usuarios._table')
    </x-remote-table>
</x-layouts.app>
