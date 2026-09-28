<x-modal :title="'Depósito · '.soles($deposito->monto)" :subtitle="fecha($deposito->fecha).' · '.$deposito->cuentaBancaria?->nombreMostrar()" icon="building-library">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Cuenta</dt><dd>{{ $deposito->cuentaBancaria?->nombreMostrar() }}</dd></div>
                <div><dt>Empresa</dt><dd>{{ $deposito->empresa?->nombre ?? '—' }}</dd></div>
                <div><dt>Operación</dt><dd>{{ $deposito->numero_operacion ?: '—' }}</dd></div>
                <div><dt>Responsable</dt><dd>{{ $deposito->chofer?->alias ?? '—' }}</dd></div>
                <div><dt>Depositante</dt><dd>{{ $deposito->depositante ?: '—' }}</dd></div>
                <div><dt>Registró</dt><dd>{{ $deposito->user?->name ?? 'Importado' }}</dd></div>
                <div class="col-span-full"><dt>Observaciones</dt><dd>{{ $deposito->observaciones ?: '—' }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$deposito"/></div>
    </div>
</x-modal>
