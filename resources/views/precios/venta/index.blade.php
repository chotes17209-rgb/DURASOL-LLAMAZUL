<x-layouts.app title="Precios de venta" breadcrumb="Precios">
    <x-slot:actions>
        <a href="{{ route('precios.venta.historial') }}" class="btn btn-secondary"><x-heroicon-o-clock class="h-4 w-4"/> Historial de cambios</a>
        <button class="btn btn-primary" data-modal-url="{{ route('precios.venta.ajuste') }}" data-modal-size="md"><x-heroicon-o-arrows-up-down class="h-4 w-4"/> Subir / bajar precios</button>
    </x-slot:actions>
    <div class="mb-4 rounded bg-brand-50 p-4 text-sm text-brand-900 border border-brand-100">
        Cada cliente tiene su propio precio por producto. Al modificar un precio se guarda como un registro nuevo con fecha de vigencia,
        así puedes ver cuándo y quién lo cambió. Usa <b>Subir / bajar precios</b> cuando Solgas cambie su precio para ajustar a todos los clientes a la vez.
    </div>
    <x-remote-table :url="route('precios.venta.index')">
        <x-slot:filters>
            <x-search placeholder="Buscar cliente..."/>
            <x-field.select name="chofer_id" :options="$choferes" placeholder="Todos los choferes" class="w-52" :selected="request('chofer_id')"/>
        </x-slot:filters>
        @include('precios.venta._table')
    </x-remote-table>
</x-layouts.app>
