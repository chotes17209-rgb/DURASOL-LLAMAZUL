<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Registró</th><th class="text-right">Ingreso</th><th class="text-right">Egreso</th><th></th></tr></thead>
        <tbody>
        @forelse ($movimientos as $m)
            <tr>
                <td>{{ fecha($m->fecha) }}</td>
                <td><x-badge :color="$m->tipo === 'egreso' ? 'red' : 'green'">{{ $m->categoria->label() }}</x-badge></td>
                <td class="text-sm">{{ $m->descripcion }} @if($m->esAutomatico())<x-badge color="slate">auto</x-badge>@endif</td>
                <td class="text-xs">{{ $m->user?->name ?? 'Importado' }}</td>
                <td class="text-right tabular-nums text-emerald-600">{{ $m->tipo === 'ingreso' ? soles($m->monto) : '' }}</td>
                <td class="text-right tabular-nums text-rose-600">{{ $m->tipo === 'egreso' ? soles($m->monto) : '' }}</td>
                <td><x-row-actions size="md" :show="route('caja.movimientos.show', $m)" :edit="$m->esAutomatico() ? null : route('caja.movimientos.edit', $m)" :delete="$m->esAutomatico() ? null : route('caja.movimientos.destroy', $m)"/></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty text="No hay movimientos de caja en el rango."/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $movimientos->links() }}
