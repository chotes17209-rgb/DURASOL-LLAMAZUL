{{-- Historial de precios de un cliente agrupado por producto. --}}
@if ($historial->isEmpty())
    <x-empty title="Sin precios" text="Este cliente aún no tiene precios asignados." icon="tag"/>
@else
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($historial as $productoId => $registros)
            <div class="rounded-2xl ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <p class="font-semibold text-slate-900">{{ $productos[$productoId]->codigo ?? '?' }} <span class="text-xs font-normal text-slate-500">{{ $productos[$productoId]->nombre ?? '' }}</span></p>
                    <x-badge color="blue">Actual {{ soles($registros->first()->precio) }}</x-badge>
                </div>
                <ol class="divide-y divide-slate-100">
                    @foreach ($registros as $idx => $r)
                        @php($anterior = $registros[$idx + 1] ?? null)
                        <li class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                            <div>
                                <p class="font-semibold tabular-nums">{{ soles($r->precio) }}
                                    @if ($anterior)
                                        @php($dif = (float) $r->precio - (float) $anterior->precio)
                                        <span class="ml-1 text-xs {{ $dif > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $dif > 0 ? '▲' : '▼' }} {{ num(abs($dif), 2) }}</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-slate-400">desde {{ fecha($r->vigente_desde) }} · {{ $r->motivo }} · {{ $r->user?->name ?? 'Importado' }}</p>
                            </div>
                            @if (auth()->user()->hasRole('liquidaciones'))
                                <button type="button" class="btn-icon danger" title="Eliminar registro" data-delete-url="{{ route('precios.venta.destroy', $r) }}" data-text="Se quitará este precio del historial."><x-heroicon-o-trash class="h-4 w-4"/></button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
@endif
