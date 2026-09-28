<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Cód.</th><th>Cliente</th><th>Chofer</th>
            @foreach ($productos as $p)<th class="text-right">{{ $p->codigo }}</th>@endforeach<th></th></tr></thead>
        <tbody>
        @forelse ($clientes as $c)
            <tr>
                <td class="font-mono text-xs text-slate-500">{{ $c->codigo }}</td>
                <td><p class="font-semibold text-slate-900">{{ $c->nombre }}</p><p class="text-xs text-slate-500">{{ $c->conocido_como }}</p></td>
                <td>{{ $c->chofer?->alias ?? '—' }}</td>
                @foreach ($productos as $p)
                    <td class="text-right tabular-nums">{{ isset($vigentes[$c->id][$p->id]) ? num($vigentes[$c->id][$p->id], 2) : '—' }}</td>
                @endforeach
                <td>
                    <x-row-actions :show="route('precios.venta.show', $c)" :edit="route('precios.venta.edit', $c)" size="xl"/>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ 4 + $productos->count() }}"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $clientes->links() }}
