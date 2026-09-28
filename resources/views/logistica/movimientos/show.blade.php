<x-modal :title="$movimiento->tipo->label()" :subtitle="fecha($movimiento->fecha).' · '.$movimiento->referencia" icon="adjustments-horizontal">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'kardex' => 'Movimientos de stock', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Producto</dt><dd>{{ $movimiento->producto?->codigo }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $movimiento->estado->label() }}</dd></div>
                <div><dt>Empresa</dt><dd>{{ $movimiento->empresa?->nombre ?? '—' }}</dd></div>
                <div><dt>Cantidad</dt><dd>{{ $movimiento->sentido === 'salida' ? '−' : '+' }}{{ num($movimiento->cantidad) }}</dd></div>
                <div><dt>Registró</dt><dd>{{ $movimiento->user?->name ?? 'Importado' }}</dd></div>
                <div class="col-span-full"><dt>Observaciones</dt><dd>{{ $movimiento->observaciones ?: '—' }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'kardex'" x-cloak>@include('logistica.stock._movimientos', ['movimientos' => $movimiento->movimientos])</div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$movimiento"/></div>
    </div>
</x-modal>
