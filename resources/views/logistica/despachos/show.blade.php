<x-modal :title="'Despacho '.$despacho->chofer->alias.' · vuelta '.$despacho->vuelta" :subtitle="fecha($despacho->fecha).' · '.$despacho->tipo->label().($despacho->destino ? ' · '.$despacho->destino : '')" icon="truck">
    <div x-data="{ tab: 'detalle' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-tabs :tabs="['detalle' => 'Detalle', 'cuadre' => 'Cuadre con liquidación', 'kardex' => 'Movimientos de stock', 'historial' => 'Historial']" class="mb-0"/>
            <div class="flex gap-2">
                @if ($despacho->estado !== \App\Enums\EstadoDespacho::Anulado)
                    <button class="btn btn-success btn-sm" data-modal-url="{{ route('logistica.despachos.retorno', $despacho) }}" data-modal-size="xl"><x-heroicon-o-arrow-uturn-left class="h-4 w-4"/> {{ $despacho->estado === \App\Enums\EstadoDespacho::Retornado ? 'Corregir retorno' : 'Registrar retorno' }}</button>
                    <button class="btn btn-danger btn-sm" data-action-url="{{ route('logistica.despachos.anular', $despacho) }}" data-confirm="¿Anular este despacho?" data-text="Se revertirá su efecto en el stock." data-input="Motivo de la anulación" data-danger="1" data-icon="warning"><x-heroicon-o-no-symbol class="h-4 w-4"/> Anular</button>
                @endif
            </div>
        </div>
        <div class="mt-5" x-show="tab === 'detalle'">
            <dl class="dl-grid mb-5">
                <div><dt>Estado</dt><dd><x-status :value="$despacho->estado"/></dd></div>
                <div><dt>Vehículo</dt><dd>{{ $despacho->vehiculo?->placa ?? '—' }}</dd></div>
                <div><dt>Horario</dt><dd>{{ $despacho->hora_salida ? substr($despacho->hora_salida, 0, 5) : '—' }} → {{ $despacho->hora_retorno ? substr($despacho->hora_retorno, 0, 5) : '—' }}</dd></div>
                <div><dt>Registró</dt><dd>{{ $despacho->user?->name ?? 'Importado' }}</dd></div>
                @if ($despacho->observaciones)<div class="col-span-2"><dt>Observaciones</dt><dd class="whitespace-pre-line">{{ $despacho->observaciones }}</dd></div>@endif
            </dl>
            <table class="table table-compact">
                <thead><tr><th>Producto</th><th>Empresa</th><th class="text-right">Salieron</th><th class="text-right">Regresan llenos</th><th class="text-right">Vacíos</th><th class="text-right">Colores</th><th class="text-right">Cambios</th><th class="text-right">Vendidos</th></tr></thead>
                <tbody>
                @foreach ($despacho->detalles as $d)
                    <tr><td class="font-mono font-bold">{{ $d->producto?->codigo }}</td><td>{{ $d->empresa?->nombre }}</td><td class="text-right">{{ $d->llenos_salida }}</td><td class="text-right">{{ $d->llenos_retorno }}</td>
                        <td class="text-right">{{ $d->vacios_retorno }}</td><td class="text-right">{{ $d->colores_retorno }}</td><td class="text-right">{{ $d->cambios_retorno }}</td><td class="text-right font-bold text-brand-700">{{ $d->vendidos() }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5" x-show="tab === 'cuadre'" x-cloak>
            <p class="mb-3 text-sm text-slate-500">Balones vendidos por <b>{{ $despacho->chofer->alias }}</b> el {{ fecha($despacho->fecha) }} según logística ({{ $despachosDia->count() }} vuelta(s) retornadas) contra lo registrado en sus liquidaciones.</p>
            <table class="table table-compact">
                <thead><tr><th>Producto</th><th class="text-right">Según logística</th><th class="text-right">Según liquidación</th><th class="text-center">Diferencia</th></tr></thead>
                <tbody>
                @forelse ($productos as $p)
                    @php($log = $vendidoLogistica[$p->id] ?? 0) @php($liq = (int) ($liquidado[$p->id] ?? 0))
                    <tr><td class="font-mono font-bold">{{ $p->codigo }}</td><td class="text-right">{{ $log }}</td><td class="text-right">{{ $liq }}</td>
                        <td class="text-center"><x-badge :color="$log === $liq ? 'green' : 'red'">{{ $log === $liq ? 'Cuadra' : ($liq - $log > 0 ? '+' : '').($liq - $log) }}</x-badge></td></tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-400">Aún no hay retornos ni liquidaciones para comparar.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5" x-show="tab === 'kardex'" x-cloak>@include('logistica.stock._movimientos', ['movimientos' => $despacho->movimientos])</div>
        <div class="mt-5" x-show="tab === 'historial'" x-cloak><x-history :model="$despacho"/></div>
    </div>
</x-modal>
