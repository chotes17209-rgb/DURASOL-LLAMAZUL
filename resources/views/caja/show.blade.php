<x-modal :title="$movimiento->categoria->label().' · '.soles($movimiento->monto)" :subtitle="$movimiento->descripcion" icon="banknotes">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Fecha</dt><dd>{{ fecha($movimiento->fecha) }}</dd></div>
                <div><dt>Tipo</dt><dd>{{ ucfirst($movimiento->tipo) }}</dd></div>
                <div><dt>Monto</dt><dd>{{ soles($movimiento->monto) }}</dd></div>
                <div><dt>Empresa</dt><dd>{{ $movimiento->empresa?->nombre ?? 'General' }}</dd></div>
                <div><dt>Registró</dt><dd>{{ $movimiento->user?->name ?? 'Importado' }}</dd></div>
                <div><dt>Origen</dt><dd>{{ $movimiento->esAutomatico() ? config('erp.modelos.'.$movimiento->origen_type).' '.($movimiento->origen?->auditLabel() ?? '') : 'Manual' }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$movimiento"/></div>
    </div>
</x-modal>
