<x-modal :title="$producto->codigo.' · '.$producto->nombre" icon="fire">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Código</dt><dd>{{ $producto->codigo }}</dd></div>
                <div><dt>Nombre</dt><dd>{{ $producto->nombre }}</dd></div>
                <div><dt>Marca</dt><dd>{{ $producto->marca ?: '—' }}</dd></div>
                <div><dt>Tipo</dt><dd>{{ ucfirst($producto->tipo) }}</dd></div>
                <div><dt>Capacidad</dt><dd>{{ $producto->capacidad_kg ? $producto->capacidad_kg.' kg' : '—' }}</dd></div>
                <div><dt>Envase</dt><dd>{{ $producto->envase?->codigo ?? '—' }}</dd></div>
                <div><dt>Compra en planta</dt><dd>{{ $producto->se_compra_en_planta ? 'Sí' : 'No' }}</dd></div>
                <div><dt>Controla stock</dt><dd>{{ $producto->controla_stock ? 'Sí' : 'No' }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $producto->activo ? 'Activo' : 'Inactivo' }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$producto"/></div>
    </div>
</x-modal>
