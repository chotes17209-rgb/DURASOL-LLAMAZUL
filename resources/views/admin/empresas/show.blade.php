<x-modal :title="$empresa->nombre" :subtitle="$empresa->razon_social" icon="briefcase">
    <div x-data="{ tab: 'detalle' }">
        <div class="tabs mb-5">
            <button class="tab" :class="tab === 'detalle' && 'active'" @click="tab = 'detalle'">Detalle</button>
            <button class="tab" :class="tab === 'instalaciones' && 'active'" @click="tab = 'instalaciones'">Instalaciones ({{ $empresa->instalaciones->count() }})</button>
            <button class="tab" :class="tab === 'historial' && 'active'" @click="tab = 'historial'">Historial</button>
        </div>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Nombre</dt><dd>{{ $empresa->nombre }}</dd></div>
                <div><dt>RUC</dt><dd>{{ $empresa->ruc ?: '—' }}</dd></div>
                <div><dt>Teléfono</dt><dd>{{ $empresa->telefono ?: '—' }}</dd></div>
                <div class="col-span-2"><dt>Dirección</dt><dd>{{ $empresa->direccion ?: '—' }}</dd></div>
                <div><dt>Estado</dt><dd><x-badge :color="$empresa->activo ? 'green' : 'red'">{{ $empresa->activo ? 'Activa' : 'Inactiva' }}</x-badge></dd></div>
            </dl>
        </div>
        <div x-show="tab === 'instalaciones'" x-cloak>
            <table class="table table-compact">
                <thead><tr><th>Código</th><th>Instalación</th><th>Chofer</th><th>Vehículo</th></tr></thead>
                <tbody>
                @forelse ($empresa->instalaciones as $i)
                    <tr><td class="font-mono">{{ $i->codigo }}</td><td>{{ $i->nombre }}</td><td>{{ $i->chofer?->alias ?? '—' }}</td><td>{{ $i->vehiculo?->placa ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-400">Sin instalaciones.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$empresa"/></div>
    </div>
</x-modal>
