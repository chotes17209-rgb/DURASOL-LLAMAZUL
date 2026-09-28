<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th>Estado</th><th>Empresa</th><th>Referencia</th><th class="text-right">Cantidad</th><th>Registró</th><th></th></tr></thead>
        <tbody>
        @forelse ($movimientos as $m)
            <tr>
                <td>{{ fecha($m->fecha) }}</td>
                <td><x-badge color="blue">{{ $m->tipo->label() }}</x-badge></td>
                <td class="font-mono">{{ $m->producto?->codigo }}</td>
                <td>{{ $m->estado->label() }}</td>
                <td>{{ $m->empresa?->nombre ?? '—' }}</td>
                <td class="text-xs">{{ $m->referencia }}</td>
                <td class="text-right font-semibold {{ $m->sentido === 'salida' ? 'text-rose-600' : 'text-emerald-600' }}">{{ $m->sentido === 'salida' ? '−' : '+' }}{{ num($m->cantidad) }}</td>
                <td class="text-xs">{{ $m->user?->name ?? 'Importado' }}</td>
                <td><x-row-actions size="md" :show="route('logistica.movimientos.show', $m)" :edit="route('logistica.movimientos.edit', $m)" :delete="route('logistica.movimientos.destroy', $m)"/></td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $movimientos->links() }}
