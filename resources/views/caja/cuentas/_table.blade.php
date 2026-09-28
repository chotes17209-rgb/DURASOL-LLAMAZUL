<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Banco</th><th>Alias</th><th>Número</th><th>Empresa</th><th>Moneda</th><th class="text-right">Total depositado</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($cuentas as $c)
            <tr>
                <td class="font-semibold">{{ $c->banco }}</td><td>{{ $c->alias }}</td><td class="font-mono text-xs">{{ $c->numero ?: '—' }}</td>
                <td>{{ $c->empresa?->nombre ?? '—' }}</td><td>{{ $c->moneda }}</td><td class="text-right tabular-nums">{{ soles($c->total_depositado) }}</td>
                <td><x-badge :color="$c->activo ? 'green' : 'red'">{{ $c->activo ? 'Activa' : 'Inactiva' }}</x-badge></td>
                <td><x-row-actions size="md" :show="route('cuentas-bancarias.show', $c)" :edit="route('cuentas-bancarias.edit', $c)" :delete="route('cuentas-bancarias.destroy', $c)"/></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $cuentas->links() }}
