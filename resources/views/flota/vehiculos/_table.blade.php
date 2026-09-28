<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Placa</th><th>Vehículo</th><th>Chofer(es)</th><th>SOAT</th><th>Rev. técnica</th><th>DGH</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($vehiculos as $v)
            @php($docs = $v->estadoDocumentos())
            <tr>
                <td><span class="font-mono font-semibold text-slate-800">{{ $v->placa }}</span></td>
                <td>
                    <p class="font-medium text-slate-900">{{ \App\Models\Vehiculo::TIPOS[$v->tipo] ?? $v->tipo }} {{ $v->marca }} {{ $v->modelo }}</p>
                    <p class="text-xs text-slate-500">{{ $v->anio }} {{ $v->empresa ? '· '.$v->empresa->nombre : '' }} {{ $v->capacidad_balones ? '· '.$v->capacidad_balones.' balones' : '' }}</p>
                </td>
                <td class="text-xs">{{ $v->choferes->pluck('alias')->join(', ') ?: '—' }}</td>
                @foreach (['soat', 'revision_tecnica', 'dgh'] as $tipo)
                    <td>
                        <x-status :value="$docs[$tipo]['estado']"/>
                        @if ($docs[$tipo]['documento']?->fecha_vencimiento)
                            <p class="mt-0.5 text-[12px] text-slate-400">{{ $docs[$tipo]['documento']->fecha_vencimiento->format('d/m/Y') }}</p>
                        @endif
                    </td>
                @endforeach
                <td><x-status :value="$v->estado"/></td>
                <td><x-row-actions size="xl" :show="route('vehiculos.show', $v)" :edit="route('vehiculos.edit', $v)" :delete="route('vehiculos.destroy', $v)"/></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $vehiculos->links() }}
