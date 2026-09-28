<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Cuenta</th><th>Empresa</th><th>Responsable</th><th>Depositante</th><th>Operación</th><th class="text-right">Monto</th><th></th></tr></thead>
        <tbody>
        @forelse ($depositos as $d)
            <tr>
                <td>{{ fecha($d->fecha) }}</td>
                <td>{{ $d->cuentaBancaria?->nombreMostrar() }}</td>
                <td>{{ $d->empresa?->nombre ?? '—' }}</td>
                <td>{{ $d->chofer?->alias ?? '—' }}</td>
                <td>{{ $d->depositante ?: '—' }}</td>
                <td class="font-mono text-xs">{{ $d->numero_operacion ?: '—' }}</td>
                <td class="text-right font-semibold tabular-nums">{{ soles($d->monto) }}</td>
                <td><x-row-actions size="md" :show="route('caja.depositos.show', $d)" :edit="route('caja.depositos.edit', $d)" :delete="route('caja.depositos.destroy', $d)"/></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty/></td></tr>
        @endforelse
        </tbody>
        @if ($depositos->isNotEmpty())<tfoot><tr><td colspan="6">Total página</td><td class="text-right">{{ soles($depositos->sum('monto')) }}</td><td></td></tr></tfoot>@endif
    </table>
</div>
{{ $depositos->links() }}
