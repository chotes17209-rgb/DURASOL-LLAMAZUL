<x-modal :title="'Crédito · '.$cuenta->cliente?->nombre" :subtitle="fecha($cuenta->fecha).' · '.$cuenta->observaciones" icon="credit-card">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Monto</dt><dd>{{ soles($cuenta->monto) }}</dd></div>
                <div><dt>Saldo</dt><dd class="font-bold">{{ soles($cuenta->saldo) }}</dd></div>
                <div><dt>Estado</dt><dd><x-status :value="$cuenta->estado"/></dd></div>
                <div><dt>Liquidación</dt><dd>{{ $cuenta->liquidacion?->codigo ?? 'Importado del Excel' }}</dd></div>
                <div><dt>Chofer</dt><dd>{{ $cuenta->liquidacion?->chofer?->alias ?? '—' }}</dd></div>
                <div><dt>Antigüedad</dt><dd>{{ $cuenta->diasVencida() }} días</dd></div>
            </dl>
            <p class="mt-5 mb-2 text-sm font-semibold">Pagos aplicados</p>
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th>Método</th><th>Registró</th><th class="text-right">Monto</th></tr></thead>
                <tbody>
                @forelse ($cuenta->cobranzas as $c)
                    <tr><td>{{ fecha($c->fecha) }}</td><td>{{ $c->metodo_pago->label() }}</td><td>{{ $c->user?->name ?? '—' }}</td><td class="text-right">{{ soles($c->monto) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-400">Sin pagos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$cuenta"/></div>
    </div>
</x-modal>
