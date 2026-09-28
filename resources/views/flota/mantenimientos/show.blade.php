<x-modal :title="'Mantenimiento · '.$mantenimiento->vehiculo?->placa" :subtitle="$mantenimiento->descripcion" icon="wrench-screwdriver">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Fecha</dt><dd>{{ fecha($mantenimiento->fecha) }}</dd></div>
                <div><dt>Tipo</dt><dd>{{ \App\Models\VehiculoMantenimiento::TIPOS[$mantenimiento->tipo] ?? $mantenimiento->tipo }}</dd></div>
                <div><dt>Costo</dt><dd>{{ soles($mantenimiento->costo) }}</dd></div>
                <div><dt>Taller</dt><dd>{{ $mantenimiento->taller ?: '—' }}</dd></div>
                <div><dt>Kilometraje</dt><dd>{{ $mantenimiento->kilometraje ? num($mantenimiento->kilometraje).' km' : '—' }}</dd></div>
                <div><dt>Próximo</dt><dd>{{ fecha($mantenimiento->proximo_fecha) ?: '—' }} {{ $mantenimiento->proximo_kilometraje ? '· '.num($mantenimiento->proximo_kilometraje).' km' : '' }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$mantenimiento"/></div>
    </div>
</x-modal>
