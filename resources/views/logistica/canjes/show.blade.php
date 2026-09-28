<x-modal :title="'Canje con '.$canje->contraparte" :subtitle="fecha($canje->fecha)" icon="arrows-right-left">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'kardex' => 'Movimientos de stock', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Balón</dt><dd>{{ $canje->producto?->capacidad_kg }} kg</dd></div>
                <div><dt>Colores entregados</dt><dd>{{ num($canje->colores_entregados) }}</dd></div>
                <div><dt>Plomos recibidos</dt><dd>{{ num($canje->plomos_recibidos) }}</dd></div>
                <div><dt>Registró</dt><dd>{{ $canje->user?->name ?? 'Importado' }}</dd></div>
                <div class="col-span-2"><dt>Observaciones</dt><dd>{{ $canje->observaciones ?: '—' }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'kardex'" x-cloak>@include('logistica.stock._movimientos', ['movimientos' => $canje->movimientos])</div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$canje"/></div>
    </div>
</x-modal>
