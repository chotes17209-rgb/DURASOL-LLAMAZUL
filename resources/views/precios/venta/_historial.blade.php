<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Vigente desde</th><th>Cliente</th><th>Producto</th><th class="text-right">Precio</th><th>Motivo</th><th>Registrado por</th><th>Fecha registro</th></tr></thead>
        <tbody>
        @forelse ($registros as $r)
            <tr>
                <td>{{ fecha($r->vigente_desde) }}</td>
                <td><span class="font-mono text-xs text-slate-400">{{ $r->cliente?->codigo }}</span> {{ $r->cliente?->nombre }}</td>
                <td class="font-mono">{{ $r->producto?->codigo }}</td>
                <td class="text-right font-semibold tabular-nums">{{ soles($r->precio) }}</td>
                <td class="text-xs">{{ $r->motivo }}</td>
                <td>{{ $r->user?->name ?? 'Importado' }}</td>
                <td class="text-xs text-slate-500">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $registros->links() }}
