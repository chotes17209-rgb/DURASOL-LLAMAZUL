<x-layouts.app title="Detalle de ventas" breadcrumb="Reportes">
    <x-slot:actions>
        <a href="{{ route('reportes.ventas.exportar', request()->query()) }}" class="btn btn-secondary" id="exportar-ventas"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/> Exportar a Excel (CSV)</a>
    </x-slot:actions>
    <x-remote-table :url="route('reportes.ventas')">
        <x-slot:filters>
            <x-field.input name="desde" label="Desde" type="date" :value="request('desde', today()->startOfMonth()->format('Y-m-d'))" class="w-40"/>
            <x-field.input name="hasta" label="Hasta" type="date" :value="request('hasta', today()->format('Y-m-d'))" class="w-40"/>
            <x-field.select name="chofer_id" label="Chofer" :options="$choferes" placeholder="Todos" class="w-40" :selected="request('chofer_id')"/>
            <x-field.select name="empresa_id" label="Empresa" :options="$empresas" placeholder="Todas" class="w-40" :selected="request('empresa_id')"/>
            <x-field.select name="producto_id" label="Producto" :options="$productos" placeholder="Todos" class="w-32" :selected="request('producto_id')"/>
            <x-field.select name="solo_credito" label="Pago" :options="['1' => 'Solo créditos']" placeholder="Todos" class="w-40" :selected="request('solo_credito')"/>
            <div class="min-w-56 flex-1"><label class="form-label">Cliente</label><input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Buscar cliente..."></div>
        </x-slot:filters>
        @include('reportes._ventas')
    </x-remote-table>
    @push('scripts')
    <script>
        document.getElementById('exportar-ventas').addEventListener('click', (e) => {
            const form = document.querySelector('[data-table-filters]');
            const params = new URLSearchParams(new FormData(form));
            e.currentTarget.href = '{{ route('reportes.ventas.exportar') }}?' + params.toString();
        });
    </script>
    @endpush
</x-layouts.app>
