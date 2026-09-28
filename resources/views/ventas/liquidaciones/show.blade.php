@php($l = $liquidacion)
<x-modal :title="'Liquidación '.$l->codigo" :subtitle="$l->chofer->alias.' · venta '.fecha($l->fecha_venta).' · liquidada '.fecha($l->fecha_liquidacion)" icon="clipboard-document-check">
    <div x-data="{ tab: 'resumen' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-tabs :tabs="['resumen' => 'Resumen', 'ventas' => 'Ventas ('.$l->items->count().')', 'otros' => 'FISE, cobranzas y gastos', 'historial' => 'Historial']" class="mb-0"/>
            <div class="flex flex-wrap gap-2">
                @if ($l->esEditable())
                    <a href="{{ route('liquidaciones.edit', $l) }}" class="btn btn-secondary btn-sm"><x-heroicon-o-pencil-square class="h-4 w-4"/> Editar</a>
                    <button class="btn btn-success btn-sm" data-modal-url="{{ route('liquidaciones.cerrar', $l) }}" data-modal-size="md"><x-heroicon-o-lock-closed class="h-4 w-4"/> Cerrar</button>
                    <button class="btn btn-danger btn-sm" data-action-url="{{ route('liquidaciones.anular', $l) }}" data-confirm="¿Anular la liquidación {{ $l->codigo }}?" data-input="Motivo de la anulación" data-danger="1" data-icon="warning"><x-heroicon-o-no-symbol class="h-4 w-4"/> Anular</button>
                @elseif ($l->estado === \App\Enums\EstadoLiquidacion::Cerrada && auth()->user()->isAdmin())
                    <button class="btn btn-warning btn-sm" data-action-url="{{ route('liquidaciones.reabrir', $l) }}" data-confirm="¿Reabrir la liquidación {{ $l->codigo }}?" data-text="Se revertirán sus créditos, cobranzas y el ingreso a caja." data-icon="warning"><x-heroicon-o-lock-open class="h-4 w-4"/> Reabrir</button>
                @endif
            </div>
        </div>

        <div class="mt-5" x-show="tab === 'resumen'">
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded bg-slate-50 p-4 border border-line"><p class="kpi-label">Venta</p><p class="text-xl font-bold">{{ soles($l->total_venta) }}</p></div>
                <div class="rounded bg-rose-50 p-4 border border-rose-200"><p class="kpi-label">Crédito</p><p class="text-xl font-bold text-rose-700">{{ soles($l->total_credito) }}</p></div>
                <div class="rounded bg-violet-50 p-4 border border-violet-200"><p class="kpi-label">Vouchers + FISE</p><p class="text-xl font-bold text-violet-700">{{ soles($l->total_vouchers + $l->total_fises) }}</p></div>
                <div class="rounded bg-brand-600 p-4 text-white"><p class="text-xs font-semibold uppercase tracking-wide text-brand-100">Efectivo</p><p class="text-xl font-bold">{{ soles($l->efectivo_esperado) }}</p>
                    @if ($l->efectivo_entregado !== null)<p class="text-xs text-brand-100">Entregado {{ soles($l->efectivo_entregado) }} ({{ $l->diferencia >= 0 ? '+' : '' }}{{ num($l->diferencia, 2) }})</p>@endif</div>
            </div>
            <div class="mt-5 grid gap-6 lg:grid-cols-2">
                <dl class="dl-grid !grid-cols-2">
                    <div><dt>Estado</dt><dd><x-status :value="$l->estado"/></dd></div>
                    <div><dt>Tipo</dt><dd>{{ $l->tipo->label() }}</dd></div>
                    <div><dt>Vehículo</dt><dd>{{ $l->vehiculo?->placa ?? '—' }}</dd></div>
                    <div><dt>Registró</dt><dd>{{ $l->user?->name ?? 'Importado' }}</dd></div>
                    <div><dt>Cerró</dt><dd>{{ $l->cerradaPor?->name ?? '—' }} {{ $l->cerrada_at?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Créditos generados</dt><dd>{{ $l->cuentasPorCobrar->count() }}</dd></div>
                    @if ($l->observaciones)<div class="col-span-2"><dt>Observaciones</dt><dd class="whitespace-pre-line">{{ $l->observaciones }}</dd></div>@endif
                </dl>
                <table class="table table-compact">
                    <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Vacíos dev.</th><th class="text-right">Importe</th></tr></thead>
                    <tbody>
                    @foreach ($porProducto as $p)
                        <tr><td class="font-mono font-bold">{{ $p['codigo'] }}</td><td class="text-right">{{ num($p['cantidad']) }}</td><td class="text-right">{{ num($p['vacios']) }}</td><td class="text-right">{{ soles($p['total']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5" x-show="tab === 'ventas'" x-cloak>
            <div class="max-h-[32rem] overflow-y-auto rounded border border-line">
                <table class="table table-compact">
                    <thead class="sticky top-0"><tr><th>Cliente</th><th>Prod.</th><th>Empresa</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Total</th><th class="text-right">Vacíos</th><th>Pago</th><th class="text-right">Crédito</th></tr></thead>
                    <tbody>
                    @foreach ($l->items as $i)
                        <tr>
                            <td><span class="font-mono text-xs text-slate-400">{{ $i->cliente?->codigo }}</span> {{ $i->cliente?->nombre }}</td>
                            <td class="font-mono">{{ $i->producto?->codigo }}</td>
                            <td class="text-xs">{{ $i->empresa?->nombre }}</td>
                            <td class="text-right">{{ $i->cantidad }}</td>
                            <td class="text-right">{{ num($i->precio, 2) }}</td>
                            <td class="text-right font-semibold">{{ soles($i->total) }}</td>
                            <td class="text-right">{{ $i->vacios_devueltos }}</td>
                            <td class="text-xs">{{ $i->metodo_pago->label() }} {{ $i->numero_operacion }}</td>
                            <td class="text-right text-rose-600">{{ $i->monto_credito > 0 ? soles($i->monto_credito) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 grid gap-6 lg:grid-cols-3" x-show="tab === 'otros'" x-cloak>
            <div>
                <p class="mb-2 text-sm font-semibold">FISE ({{ soles($l->total_fises) }})</p>
                <table class="table table-compact">
                    <thead><tr><th>Cliente</th><th class="text-right">Vale</th><th class="text-right">Cant.</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                    @forelse ($l->fises as $f)
                        <tr><td class="text-xs">{{ $f->cliente?->nombre ?? 'Sin cliente' }}</td><td class="text-right">{{ num($f->valor) }}</td><td class="text-right">{{ $f->cantidad }}</td><td class="text-right">{{ soles($f->subtotal) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-400">Sin FISE.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold">Cobranzas ({{ soles($l->total_cobranzas) }})</p>
                <table class="table table-compact">
                    <thead><tr><th>Cliente</th><th>Pago</th><th class="text-right">Monto</th></tr></thead>
                    <tbody>
                    @forelse ($l->cobranzas as $c)
                        <tr><td class="text-xs">{{ $c->cliente?->nombre }}</td><td class="text-xs">{{ $c->metodo_pago->label() }}</td><td class="text-right">{{ soles($c->monto) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-slate-400">Sin cobranzas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold">Gastos ({{ soles($l->total_gastos) }})</p>
                <table class="table table-compact">
                    <thead><tr><th>Concepto</th><th class="text-right">Monto</th></tr></thead>
                    <tbody>
                    @forelse ($l->gastos as $g)
                        <tr><td class="text-xs">{{ $g->concepto }} {{ $g->comprobante }}</td><td class="text-right">{{ soles($g->monto) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-slate-400">Sin gastos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5" x-show="tab === 'historial'" x-cloak><x-history :model="$l"/></div>
    </div>
</x-modal>
