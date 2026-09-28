<x-modal :title="'Guía '.$guia->numero_guia" :subtitle="$guia->empresa->nombre.' · '.$guia->instalacion?->nombreMostrar()" icon="building-office-2">
    <div x-data="{ tab: 'detalle' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-tabs :tabs="['detalle' => 'Detalle', 'kardex' => 'Movimientos de stock', 'historial' => 'Historial']" class="mb-0"/>
            <div class="flex gap-2">
                @if ($guia->estado === \App\Enums\EstadoGuia::EnTransito)
                    <button class="btn btn-success btn-sm" data-modal-url="{{ route('logistica.guias.recibir', $guia) }}" data-modal-size="xl"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/> Recibir</button>
                @endif
                @if ($guia->estado !== \App\Enums\EstadoGuia::Anulada)
                    <button class="btn btn-secondary btn-sm" data-modal-url="{{ route('logistica.guias.edit', $guia) }}" data-modal-size="xl"><x-heroicon-o-pencil-square class="h-4 w-4"/> Editar</button>
                    <button class="btn btn-danger btn-sm" data-action-url="{{ route('logistica.guias.anular', $guia) }}" data-confirm="¿Anular la guía {{ $guia->numero_guia }}?" data-text="Se revertirá su efecto en el stock." data-input="Motivo de la anulación" data-danger="1" data-icon="warning"><x-heroicon-o-no-symbol class="h-4 w-4"/> Anular</button>
                @endif
            </div>
        </div>
        <div class="mt-5" x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Estado</dt><dd><x-status :value="$guia->estado"/> @if($guia->historico)<x-badge color="slate">Histórico (no mueve stock)</x-badge>@endif</dd></div>
                <div><dt>Salida</dt><dd>{{ fecha($guia->fecha_salida) }}</dd></div>
                <div><dt>Recepción</dt><dd>{{ fecha($guia->fecha_recepcion) ?: '—' }}</dd></div>
                <div><dt>Camión</dt><dd>{{ $guia->vehiculo?->placa ?? '—' }}</dd></div>
                <div><dt>Chofer</dt><dd>{{ $guia->chofer?->alias ?? '—' }}</dd></div>
                <div><dt>Registrado por</dt><dd>{{ $guia->user?->name ?? 'Importado' }}</dd></div>
                @if ($guia->observaciones)<div class="col-span-full"><dt>Observaciones</dt><dd class="whitespace-pre-line">{{ $guia->observaciones }}</dd></div>@endif
            </dl>
            <div class="mt-6 overflow-hidden rounded-2xl ring-1 ring-slate-200">
                <table class="table table-compact">
                    <thead>
                    <tr><th>Producto</th><th class="text-right">Guía</th><th class="text-right">P. compra</th><th class="text-right">Vacíos</th><th class="text-right">Colores</th><th class="text-right">Cambios</th>
                        <th class="text-right">Llenos</th><th class="text-right">Repos. cambios</th><th class="text-right">Rechazados</th><th class="text-right">Importe</th><th class="text-center">Masa</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($guia->detalles as $d)
                        <tr>
                            <td class="font-mono font-bold">{{ $d->producto?->codigo }}</td>
                            <td class="text-right">{{ num($d->cantidad_guia) }}</td>
                            <td class="text-right">{{ num($d->precio_compra, 2) }}</td>
                            <td class="text-right">{{ num($d->vacios_enviados) }}</td>
                            <td class="text-right">{{ num($d->colores_enviados) }}</td>
                            <td class="text-right">{{ num($d->cambios_enviados) }}</td>
                            <td class="text-right font-semibold text-emerald-700">{{ num($d->llenos_recibidos) }}</td>
                            <td class="text-right">{{ num($d->cambios_repuestos) }}</td>
                            <td class="text-right text-rose-600">{{ num($d->vacios_rechazados + $d->colores_rechazados) }}</td>
                            <td class="text-right">{{ soles($d->llenos_recibidos * $d->precio_compra) }}</td>
                            <td class="text-center">
                                @if ($guia->estado === \App\Enums\EstadoGuia::Recibida)
                                    <x-badge :color="$d->totalRetornado() === $d->totalEnviado() ? 'green' : 'red'">{{ $d->totalRetornado() - $d->totalEnviado() ?: 'OK' }}</x-badge>
                                @else — @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td>Total</td><td class="text-right">{{ num($guia->totalGuia()) }}</td><td></td><td colspan="3" class="text-right">{{ num($guia->totalEnviado()) }} enviados</td><td class="text-right">{{ num($guia->totalLlenos()) }}</td><td colspan="2"></td><td class="text-right">{{ soles($guia->importeCompra()) }}</td><td></td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="mt-5" x-show="tab === 'kardex'" x-cloak>
            @include('logistica.stock._movimientos', ['movimientos' => $guia->movimientos])
        </div>
        <div class="mt-5" x-show="tab === 'historial'" x-cloak><x-history :model="$guia"/></div>
    </div>
</x-modal>
