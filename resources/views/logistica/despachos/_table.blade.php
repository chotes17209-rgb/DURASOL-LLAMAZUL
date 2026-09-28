<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Chofer</th><th class="text-center">Vuelta</th><th>Tipo</th><th>Salida</th><th class="text-right">Salieron</th><th class="text-right">Vendidos</th><th class="text-right">Vacíos</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($despachos as $d)
            <tr>
                <td class="whitespace-nowrap">{{ fecha($d->fecha) }}<p class="text-[11px] text-slate-400">{{ $d->hora_salida ? substr($d->hora_salida, 0, 5) : '' }}{{ $d->hora_retorno ? ' → '.substr($d->hora_retorno, 0, 5) : '' }}</p></td>
                <td><p class="font-bold">{{ $d->chofer?->alias }}</p><p class="text-xs text-slate-500">{{ $d->vehiculo?->placa }} {{ $d->destino ? '· '.$d->destino : '' }}</p></td>
                <td class="text-center">{{ $d->vuelta }}</td>
                <td><x-badge :color="$d->tipo === \App\Enums\TipoChofer::Ruta ? 'violet' : 'blue'">{{ $d->tipo->label() }}</x-badge></td>
                <td class="text-xs">@foreach ($d->detalles as $det)<span class="mr-1 whitespace-nowrap">{{ $det->producto?->codigo }}<sup class="text-slate-400">{{ mb_substr($det->empresa?->nombre ?? '', 0, 1) }}</sup>: <b>{{ $det->llenos_salida }}</b></span>@endforeach</td>
                <td class="text-right tabular-nums">{{ num($d->totalSalida()) }}</td>
                <td class="text-right tabular-nums font-semibold">{{ $d->estado === \App\Enums\EstadoDespacho::Retornado ? num($d->totalVendidos()) : '—' }}</td>
                <td class="text-right tabular-nums">{{ $d->estado === \App\Enums\EstadoDespacho::Retornado ? num($d->totalVacios()) : '—' }}</td>
                <td><x-status :value="$d->estado"/> @if($d->historico)<x-badge color="slate">Histórico</x-badge>@endif</td>
                <td>
                    <x-row-actions size="xl" :show="route('logistica.despachos.show', $d)" :edit="$d->estado !== \App\Enums\EstadoDespacho::Anulado ? route('logistica.despachos.edit', $d) : null" :delete="route('logistica.despachos.destroy', $d)" delete-text="Se eliminará el despacho y se revertirá su efecto en el stock.">
                        @if ($d->estado === \App\Enums\EstadoDespacho::EnRuta)
                            <button type="button" class="btn btn-success btn-sm" data-modal-url="{{ route('logistica.despachos.retorno', $d) }}" data-modal-size="xl"><x-heroicon-o-arrow-uturn-left class="h-4 w-4"/> Retorno</button>
                        @endif
                    </x-row-actions>
                </td>
            </tr>
        @empty
            <tr><td colspan="10"><x-empty title="Sin despachos" text="Registra la salida de balones de un chofer."/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $despachos->links() }}
