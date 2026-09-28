{{-- Lista de movimientos de kardex generados por un documento. --}}
@php($colores = ['lleno' => 'green', 'vacio' => 'slate', 'color' => 'violet', 'cambio' => 'amber'])
<table class="table table-compact">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Estado</th><th>Empresa</th><th>Concepto</th><th class="text-right">Cantidad</th></tr></thead>
    <tbody>
    @forelse ($movimientos as $m)
        <tr>
            <td>{{ fecha($m->fecha) }}</td>
            <td class="font-mono">{{ $m->producto?->codigo }}</td>
            <td><x-badge :color="$colores[$m->estado->value] ?? 'slate'">{{ $m->estado->label() }}</x-badge></td>
            <td>{{ $m->empresa?->nombre ?? '—' }}</td>
            <td class="text-xs">{{ $m->concepto }}</td>
            <td class="text-right font-semibold tabular-nums {{ $m->cantidad < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $m->cantidad > 0 ? '+' : '' }}{{ num($m->cantidad) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="py-6 text-center text-slate-400">Este documento no generó movimientos de stock.</td></tr>
    @endforelse
    </tbody>
</table>
