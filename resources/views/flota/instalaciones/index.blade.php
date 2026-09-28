<x-layouts.app title="Instalaciones Solgas" breadcrumb="Flota y personal">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('instalaciones.create') }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Nueva instalación</button>
    </x-slot:actions>
    <div class="mb-4 rounded bg-brand-50 p-4 text-sm text-brand-900 border border-brand-100">
        Cada instalación se identifica con un código de <b>8 dígitos</b>, pertenece a una empresa y tiene su propio precio de compra, chofer y camión designados.
    </div>
    <x-remote-table :url="route('instalaciones.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar código o nombre..."/>
            <x-field.select name="empresa_id" :options="$empresas" placeholder="Todas las empresas" class="w-52" :selected="request('empresa_id')"/>
        </x-slot:filters>
        @include('flota.instalaciones._table')
    </x-remote-table>
</x-layouts.app>
