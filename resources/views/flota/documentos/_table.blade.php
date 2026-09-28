<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Vehículo</th><th>Documento</th><th>Número</th><th>Entidad</th><th>Vencimiento</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($documentos as $d)
            <tr>
                <td><span class="font-mono font-semibold text-slate-800">{{ $d->vehiculo?->placa }}</span></td>
                <td class="font-medium">{{ $d->tipo->label() }}</td>
                <td class="font-mono text-xs">{{ $d->numero ?: '—' }}</td>
                <td>{{ $d->entidad ?: '—' }}</td>
                <td>{{ fecha($d->fecha_vencimiento) ?: '—' }}
                    @if (! is_null($d->diasParaVencer()))<p class="text-[12px] text-slate-400">{{ $d->diasParaVencer() >= 0 ? 'en '.$d->diasParaVencer().' días' : 'hace '.abs($d->diasParaVencer()).' días' }}</p>@endif
                </td>
                <td><x-status :value="$d->estadoVencimiento()"/></td>
                <td><x-row-actions size="md" :show="route('documentos.show', $d)" :edit="route('documentos.edit', $d)" :delete="route('documentos.destroy', $d)"/></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $documentos->links() }}
