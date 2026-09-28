<dl class="grid grid-cols-2 border-b border-line lg:grid-cols-4">
    <x-cifra label="Balones vendidos" :value="num($totales->cantidad)"/>
    <x-cifra label="Vacíos devueltos" :value="num($totales->vacios)"/>
    <x-cifra label="Al crédito" :value="soles($totales->credito)" tone="red"/>
    <x-cifra label="Venta total" :value="soles($totales->total)" total/>
</dl>
<div class="flex flex-wrap gap-x-5 gap-y-1 border-b border-line bg-panel px-4 py-2 text-xs text-slate-600">
    <span class="font-semibold text-slate-800">Por presentación</span>
    @foreach ($porProducto as $p)<span><b class="text-slate-800">{{ $p->codigo }}</b> {{ num($p->cantidad) }} bal. · {{ soles($p->total) }}</span>@endforeach
</div>
<div class="table-wrap">
    <table class="table table-compact">
        <thead><tr><th>Fecha</th><th>Liq.</th><th>Empresa</th><th>Responsable</th><th>Cliente</th><th>Prod.</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Total</th><th class="text-right">Vacíos</th><th class="text-right">Crédito</th><th>Pago</th></tr></thead>
        <tbody>
        @forelse ($items as $i)
            <tr>
                <td>{{ fecha($i->liquidacion->fecha_venta) }}</td>
                <td class="whitespace-nowrap"><button type="button" class="font-mono text-xs text-brand-600 hover:underline" data-modal-url="{{ route('liquidaciones.show', $i->liquidacion_id) }}" data-modal-size="xl">{{ $i->liquidacion->codigo }}</button></td>
                <td class="text-xs">{{ $i->empresa?->nombre }}</td>
                <td>{{ $i->liquidacion->chofer?->alias }}</td>
                <td><span class="font-mono text-xs text-slate-400">{{ $i->cliente?->codigo }}</span> {{ $i->cliente?->nombre }}</td>
                <td class="font-mono">{{ $i->producto?->codigo }}</td>
                <td class="text-right">{{ num($i->cantidad) }}</td>
                <td class="text-right">{{ num($i->precio, 2) }}</td>
                <td class="text-right font-semibold">{{ num($i->total, 2) }}</td>
                <td class="text-right">{{ $i->vacios_devueltos ?: '' }}</td>
                <td class="text-right text-red-700">{{ $i->monto_credito > 0 ? num($i->monto_credito, 2) : '' }}</td>
                <td class="text-xs">{{ $i->metodo_pago->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="12"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $items->links() }}
