<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Código</th><th>Venta / liquida</th><th>Chofer</th><th class="text-right">Balones</th><th class="text-right">Venta</th><th class="text-right">Crédito</th>
            <th class="text-right">Vouchers</th><th class="text-right">FISE</th><th class="text-right">Efectivo</th><th class="text-right">Entregado</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($liquidaciones as $l)
            <tr>
                <td class="font-mono text-xs font-semibold">{{ $l->codigo }} @if($l->historico)<x-badge color="slate">Excel</x-badge>@endif</td>
                <td class="whitespace-nowrap">{{ fecha($l->fecha_venta) }}<p class="text-[11px] text-slate-400">liq. {{ fecha($l->fecha_liquidacion) }}</p></td>
                <td><p class="font-bold">{{ $l->chofer?->alias }}</p><p class="text-[11px] text-slate-400">{{ $l->tipo->label() }}</p></td>
                <td class="text-right tabular-nums">{{ num($l->balones) }}</td>
                <td class="text-right font-semibold tabular-nums">{{ soles($l->total_venta) }}</td>
                <td class="text-right tabular-nums text-rose-600">{{ $l->total_credito > 0 ? soles($l->total_credito) : '—' }}</td>
                <td class="text-right tabular-nums">{{ $l->total_vouchers > 0 ? soles($l->total_vouchers) : '—' }}</td>
                <td class="text-right tabular-nums text-violet-600">{{ $l->total_fises > 0 ? soles($l->total_fises) : '—' }}</td>
                <td class="text-right font-bold tabular-nums">{{ soles($l->efectivo_esperado) }}</td>
                <td class="text-right tabular-nums">
                    {{ $l->efectivo_entregado !== null ? soles($l->efectivo_entregado) : '—' }}
                    @if ($l->efectivo_entregado !== null && abs((float) $l->diferencia) >= 0.01)
                        <p class="text-[11px] font-semibold {{ $l->diferencia > 0 ? 'text-sky-600' : 'text-rose-600' }}">{{ $l->diferencia > 0 ? '+' : '' }}{{ num($l->diferencia, 2) }}</p>
                    @endif
                </td>
                <td><x-status :value="$l->estado"/></td>
                <td>
                    <x-row-actions size="xl" :show="route('liquidaciones.show', $l)" :delete="$l->estado !== \App\Enums\EstadoLiquidacion::Cerrada ? route('liquidaciones.destroy', $l) : null">
                        @if ($l->esEditable())
                            <a href="{{ route('liquidaciones.edit', $l) }}" class="btn-icon info" title="Editar"><x-heroicon-o-pencil-square class="h-4 w-4"/></a>
                            <button type="button" class="btn btn-success btn-sm" data-modal-url="{{ route('liquidaciones.cerrar', $l) }}" data-modal-size="md"><x-heroicon-o-lock-closed class="h-4 w-4"/> Cerrar</button>
                        @endif
                    </x-row-actions>
                </td>
            </tr>
        @empty
            <tr><td colspan="12"><x-empty title="Sin liquidaciones" text="Crea la liquidación del día anterior de cada chofer."/></td></tr>
        @endforelse
        </tbody>
        @if ($liquidaciones->isNotEmpty())
            <tfoot><tr><td colspan="3">Total página</td><td class="text-right">{{ num($liquidaciones->sum('balones')) }}</td><td class="text-right">{{ soles($liquidaciones->sum('total_venta')) }}</td>
                <td class="text-right">{{ soles($liquidaciones->sum('total_credito')) }}</td><td class="text-right">{{ soles($liquidaciones->sum('total_vouchers')) }}</td><td class="text-right">{{ soles($liquidaciones->sum('total_fises')) }}</td>
                <td class="text-right">{{ soles($liquidaciones->sum('efectivo_esperado')) }}</td><td colspan="3"></td></tr></tfoot>
        @endif
    </table>
</div>
{{ $liquidaciones->links() }}
