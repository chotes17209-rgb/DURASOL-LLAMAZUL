@if ($vista === 'clientes')
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Cliente</th><th>Chofer</th><th>Teléfono</th><th class="text-center">Créditos</th><th>Deuda desde</th><th class="text-right">Deuda</th><th></th></tr></thead>
        <tbody>
        @forelse ($registros as $c)
            <tr>
                <td><span class="font-mono text-xs text-slate-400">{{ $c->codigo }}</span> <span class="font-semibold">{{ $c->nombre }}</span><p class="text-xs text-slate-500">{{ $c->conocido_como }}</p></td>
                <td>{{ $c->chofer?->alias ?? '—' }}</td>
                <td>{{ $c->telefono ?: '—' }}</td>
                <td class="text-center">{{ $c->documentos }}</td>
                <td>{{ fecha($c->desde) }} <span class="text-xs text-slate-400">({{ \Illuminate\Support\Carbon::parse($c->desde)->diffInDays(today()) }} días)</span></td>
                <td class="text-right text-base font-bold text-red-700 tabular-nums">{{ soles($c->deuda) }}</td>
                <td>
                    <x-row-actions size="xl" :show="route('creditos.cliente', $c)">
                        <button type="button" class="btn btn-success btn-sm" data-modal-url="{{ route('creditos.cobranzas.create', ['cliente_id' => $c->id]) }}" data-modal-size="md">Cobrar</button>
                    </x-row-actions>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="Sin deudas pendientes" icon="check-badge"/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@else
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Cliente</th><th>Chofer</th><th>Detalle</th><th class="text-right">Monto</th><th class="text-right">Saldo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($registros as $c)
            <tr>
                <td>{{ fecha($c->fecha) }}<p class="text-[12px] text-slate-400">{{ $c->diasVencida() }} días</p></td>
                <td>{{ $c->cliente?->nombre }}</td>
                <td>{{ $c->cliente?->chofer?->alias }}</td>
                <td class="text-xs">{{ $c->observaciones }}</td>
                <td class="text-right tabular-nums">{{ soles($c->monto) }}</td>
                <td class="text-right font-semibold tabular-nums">{{ soles($c->saldo) }}</td>
                <td><x-status :value="$c->estado"/></td>
                <td><x-row-actions size="md" :show="route('creditos.show', $c)"/></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
{{ $registros->links() }}
