<div class="grid gap-3 border-b border-line p-3 sm:grid-cols-4">
    <div class="kpi"><p class="kpi-label">Balones</p><p class="kpi-value">{{ num($totales->cantidad) }}</p></div>
    <div class="kpi"><p class="kpi-label">Venta</p><p class="kpi-value">{{ soles($totales->total) }}</p></div>
    <div class="kpi"><p class="kpi-label">Crédito</p><p class="kpi-value">{{ soles($totales->credito) }}</p></div>
    <div class="kpi"><p class="kpi-label">Vacíos devueltos</p><p class="kpi-value">{{ num($totales->vacios) }}</p></div>
    <div class="flex flex-wrap gap-2 sm:col-span-4">
        @foreach ($porProducto as $p)<span class="text-xs text-slate-600"><b>{{ $p->codigo }}</b> {{ num($p->cantidad) }} · {{ soles($p->total) }}</span>@endforeach
    </div>
</div>
<div class="table-wrap">
    <table class="table table-compact">
        <thead><tr><th>Fecha</th><th>Liq.</th><th>Empresa</th><th>Responsable</th><th>Cliente</th><th>Prod.</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Total</th><th class="text-right">Vacíos</th><th class="text-right">Crédito</th><th>Pago</th></tr></thead>
        <tbody>
        @forelse ($items as $i)
            <tr>
                <td>{{ fecha($i->liquidacion->fecha_venta) }}</td>
                <td><button type="button" class="font-mono text-xs text-brand-600 hover:underline" data-modal-url="{{ route('liquidaciones.show', $i->liquidacion_id) }}" data-modal-size="xl">{{ $i->liquidacion->codigo }}</button></td>
                <td class="text-xs">{{ $i->empresa?->nombre }}</td>
                <td>{{ $i->liquidacion->chofer?->alias }}</td>
                <td><span class="font-mono text-xs text-slate-400">{{ $i->cliente?->codigo }}</span> {{ $i->cliente?->nombre }}</td>
                <td class="font-mono">{{ $i->producto?->codigo }}</td>
                <td class="text-right">{{ num($i->cantidad) }}</td>
                <td class="text-right">{{ num($i->precio, 2) }}</td>
                <td class="text-right font-semibold">{{ num($i->total, 2) }}</td>
                <td class="text-right">{{ $i->vacios_devueltos ?: '' }}</td>
                <td class="text-right text-rose-600">{{ $i->monto_credito > 0 ? num($i->monto_credito, 2) : '' }}</td>
                <td class="text-xs">{{ $i->metodo_pago->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="12"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $items->links() }}
