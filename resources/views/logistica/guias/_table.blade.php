<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Salida</th><th>N° guía</th><th>Empresa / instalación</th><th>Camión · chofer</th><th>Detalle</th>
            <th class="text-right">Guía</th><th class="text-right">Enviados</th><th class="text-right">Llenos</th><th class="text-center">Masa</th><th class="text-right">Importe</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($guias as $g)
            <tr>
                <td class="whitespace-nowrap">{{ fecha($g->fecha_salida) }}@if($g->fecha_recepcion && ! $g->fecha_recepcion->eq($g->fecha_salida))<p class="text-[11px] text-slate-400">rec. {{ fecha($g->fecha_recepcion) }}</p>@endif</td>
                <td class="font-mono font-semibold">{{ $g->numero_guia }} @if($g->historico)<x-badge color="slate">Histórico</x-badge>@endif</td>
                <td><x-badge color="blue">{{ $g->empresa?->nombre }}</x-badge><p class="mt-0.5 text-xs text-slate-500">{{ $g->instalacion?->codigo }}</p></td>
                <td class="text-xs">{{ $g->vehiculo?->placa ?? '—' }}<p class="text-slate-500">{{ $g->chofer?->alias }}</p></td>
                <td class="text-xs">@foreach ($g->detalles as $d)<span class="mr-1 whitespace-nowrap">{{ $d->producto?->codigo }}: <b>{{ $d->cantidad_guia }}</b></span>@endforeach</td>
                <td class="text-right tabular-nums">{{ num($g->totalGuia()) }}</td>
                <td class="text-right tabular-nums">{{ num($g->totalEnviado()) }}</td>
                <td class="text-right tabular-nums">{{ $g->estado === \App\Enums\EstadoGuia::Recibida ? num($g->totalLlenos()) : '—' }}</td>
                <td class="text-center">
                    @if ($g->estado === \App\Enums\EstadoGuia::Recibida)
                        @if ($g->diferenciaMasa() === 0)<x-heroicon-s-check-circle class="mx-auto h-5 w-5 text-emerald-500"/>@else<x-badge color="red">{{ $g->diferenciaMasa() }}</x-badge>@endif
                    @else — @endif
                </td>
                <td class="text-right tabular-nums">{{ $g->estado === \App\Enums\EstadoGuia::Recibida ? soles($g->importeCompra()) : '—' }}</td>
                <td><x-status :value="$g->estado"/></td>
                <td>
                    <x-row-actions size="xl" :show="route('logistica.guias.show', $g)" :edit="$g->estado !== \App\Enums\EstadoGuia::Anulada ? route('logistica.guias.edit', $g) : null" :delete="route('logistica.guias.destroy', $g)" delete-text="Se eliminará la guía y se revertirá su efecto en el stock.">
                        @if ($g->estado === \App\Enums\EstadoGuia::EnTransito)
                            <button type="button" class="btn btn-success btn-sm" data-modal-url="{{ route('logistica.guias.recibir', $g) }}" data-modal-size="xl"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/> Recibir</button>
                        @endif
                    </x-row-actions>
                </td>
            </tr>
        @empty
            <tr><td colspan="12"><x-empty title="Sin guías" text="Registra la primera salida a planta."/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $guias->links() }}
