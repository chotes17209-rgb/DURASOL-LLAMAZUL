<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Cód.</th><th>Cliente</th><th>Dirección</th><th>Chofer</th>
            @foreach ($productos as $p)<th class="text-right">{{ $p->codigo }}</th>@endforeach
            <th class="text-right">Deuda</th><th></th></tr></thead>
        <tbody>
        @forelse ($clientes as $c)
            <tr @class(['opacity-60' => ! $c->activo])>
                <td class="font-mono text-xs text-slate-500">{{ $c->codigo }}</td>
                <td><p class="font-semibold text-slate-900">{{ $c->nombre }}</p>
                    <p class="text-xs text-slate-500">{{ $c->conocido_como }} @if($c->telefono)· {{ $c->telefono }}@endif</p></td>
                <td class="max-w-64 truncate text-xs" title="{{ $c->direccion }}">{{ $c->direccion ?: '—' }}</td>
                <td>{{ $c->chofer?->alias ?? '—' }}</td>
                @foreach ($productos as $p)
                    <td class="text-right tabular-nums">{{ isset($vigentes[$c->id][$p->id]) ? num($vigentes[$c->id][$p->id], 2) : '—' }}</td>
                @endforeach
                <td class="text-right tabular-nums {{ $c->deuda > 0 ? 'font-semibold text-red-700' : 'text-slate-400' }}">{{ $c->deuda > 0 ? soles($c->deuda) : '—' }}</td>
                <td><x-row-actions size="xl" :show="route('clientes.show', $c)" :edit="route('clientes.edit', $c)" :delete="route('clientes.destroy', $c)"/></td>
            </tr>
        @empty
            <tr><td colspan="{{ 6 + $productos->count() }}"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $clientes->links() }}
