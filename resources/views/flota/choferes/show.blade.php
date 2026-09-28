<x-modal :title="$chofer->alias" :subtitle="$chofer->nombre_completo ?: $chofer->tipo->label()" icon="identification">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'clientes' => 'Cartera de clientes ('.$chofer->clientes->count().')', 'actividad' => 'Actividad', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Nombre corto</dt><dd>{{ $chofer->alias }}</dd></div>
                <div><dt>Tipo</dt><dd>{{ $chofer->tipo->label() }}</dd></div>
                <div><dt>DNI</dt><dd>{{ $chofer->dni ?: '—' }}</dd></div>
                <div><dt>Teléfono</dt><dd>{{ $chofer->telefono ?: '—' }}</dd></div>
                <div><dt>Vehículo</dt><dd>{{ $chofer->vehiculo?->placa ?? '—' }}</dd></div>
                <div><dt>Licencia</dt><dd>{{ trim($chofer->licencia_categoria.' '.$chofer->licencia) ?: '—' }}</dd></div>
                <div><dt>Vence licencia</dt><dd>{{ fecha($chofer->licencia_vence) ?: '—' }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $chofer->activo ? 'Activo' : 'Inactivo' }}</dd></div>
                <div class="col-span-full"><dt>Observaciones</dt><dd>{{ $chofer->observaciones ?: '—' }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'clientes'" x-cloak>
            <div class="max-h-[28rem] overflow-y-auto rounded border border-line">
                <table class="table table-compact">
                    <thead class="sticky top-0"><tr><th>Cód.</th><th>Cliente</th><th>Conocido como</th><th>Dirección</th><th>Teléfono</th></tr></thead>
                    <tbody>
                    @forelse ($chofer->clientes as $cl)
                        <tr><td class="font-mono text-xs">{{ $cl->codigo }}</td><td class="font-medium">{{ $cl->nombre }}</td><td>{{ $cl->conocido_como }}</td><td class="text-xs">{{ $cl->direccion }}</td><td>{{ $cl->telefono }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-400">Sin clientes asignados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div x-show="tab === 'actividad'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold">Últimas liquidaciones</p>
                <table class="table table-compact">
                    <thead><tr><th>Código</th><th>Fecha venta</th><th class="text-right">Venta</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse ($liquidaciones as $l)
                        <tr><td class="font-mono text-xs">{{ $l->codigo }}</td><td>{{ fecha($l->fecha_venta) }}</td><td class="text-right">{{ soles($l->total_venta) }}</td><td><x-status :value="$l->estado"/></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-400">Sin liquidaciones.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold">Últimos movimientos en almacén</p>
                @include('logistica.partes._movimientos')
            </div>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$chofer"/></div>
    </div>
</x-modal>
