<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Con quién</th><th>Balón</th><th class="text-right">Colores entregados</th><th class="text-right">Plomos recibidos</th><th>Registró</th><th></th></tr></thead>
        <tbody>
        @forelse ($canjes as $c)
            <tr>
                <td>{{ fecha($c->fecha) }}</td>
                <td class="font-semibold">{{ $c->contraparte }}</td>
                <td>{{ $c->producto?->capacidad_kg }} kg</td>
                <td class="text-right text-violet-700">{{ num($c->colores_entregados) }}</td>
                <td class="text-right text-emerald-700">{{ num($c->plomos_recibidos) }}</td>
                <td class="text-xs">{{ $c->user?->name ?? 'Importado' }}</td>
                <td><x-row-actions size="md" :show="route('logistica.canjes.show', $c)" :edit="route('logistica.canjes.edit', $c)" :delete="route('logistica.canjes.destroy', $c)"/></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $canjes->links() }}
