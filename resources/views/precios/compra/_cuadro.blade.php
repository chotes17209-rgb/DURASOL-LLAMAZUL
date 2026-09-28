{{--
    Cuadro de instalaciones como lo lleva logística: empresa, responsable, código, placa, planta,
    precios de compra vigentes y si el último cambio de precio ya se validó en las facturas.
    $modo: 'instalaciones' (CRUD) o 'precios' (editar / validar precios).
--}}
@php
    $puedeEditar = auth()->user()->hasRole('logistica');
    $pendientes = $instalaciones->filter(fn ($i) => collect($vigentes[$i->id] ?? [])->contains('validado', false));
    $porEmpresa = $instalaciones->groupBy(fn ($i) => $i->empresa?->nombre)->map->count();
@endphp
<dl class="ledger mb-3 !grid-cols-2 lg:!grid-cols-4">
    @foreach ($porEmpresa as $empresa => $cantidad)
        <x-cifra :label="'Instalaciones '.$empresa" :value="num($cantidad)"/>
    @endforeach
    <x-cifra label="Precios no validados" :value="num($pendientes->count())" :tone="$pendientes->isNotEmpty() ? 'red' : 'green'" hint="instalaciones con precio sin factura"/>
    <x-cifra label="Instalaciones activas" :value="num($instalaciones->count())" total/>
</dl>
@forelse ($instalaciones->groupBy(fn ($i) => $i->empresa?->nombre) as $empresa => $lista)
    <div class="card mb-4">
        <div class="card-header">
            <p class="card-title">{{ $empresa }}</p>
            <span class="text-xs text-slate-500">{{ $lista->count() }} instalación(es)</span>
        </div>
        <div class="table-wrap">
            <table class="table table-compact table-grid">
                <thead>
                <tr>
                    <th class="w-36">Responsable</th>
                    <th class="w-28">Instalación</th>
                    <th>Placa</th>
                    <th class="w-28">Planta</th>
                    @foreach ($productos as $p)<th class="w-24 text-right">{{ $p->codigo }}</th>@endforeach
                    <th class="w-32">Estado</th>
                    <th class="w-48"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($lista as $i)
                    @php($precios = collect($vigentes[$i->id] ?? []))
                    @php($pendientes = $precios->where('validado', false)->count())
                    <tr @class(['text-slate-400' => ! $i->activo])>
                        <td class="font-semibold">{{ $i->responsable ?: $i->chofer?->alias ?: '—' }}</td>
                        <td class="font-mono">{{ $i->codigo }}</td>
                        <td class="text-xs">{{ $i->placas ?: $i->vehiculo?->placa ?: '—' }}</td>
                        <td>{{ $i->planta ?: '—' }}</td>
                        @foreach ($productos as $p)
                            @php($precio = $precios[$p->id] ?? null)
                            <td class="text-right {{ $precio && ! $precio->validado ? 'bg-amber-50' : '' }}" @if ($precio) title="Vigente desde {{ fecha($precio->vigente_desde) }}{{ $precio->validado ? '' : ' · no validado en factura' }}" @endif>
                                {{ $precio ? num($precio->precio, 2) : '' }}
                            </td>
                        @endforeach
                        <td>
                            @if ($precios->isEmpty())
                                <span class="badge badge-slate">Sin precios</span>
                            @elseif ($pendientes)
                                <span class="badge badge-amber">No validado</span>
                            @else
                                <span class="badge badge-green">Validado</span>
                            @endif
                            @unless ($i->activo)<span class="badge badge-red">Inactiva</span>@endunless
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                @if ($puedeEditar)
                                    <button class="btn btn-secondary btn-sm" data-modal-url="{{ route('precios.compra.create', ['instalacion_id' => $i->id]) }}" data-modal-size="md">Precios</button>
                                    @if ($pendientes)
                                        <button class="btn btn-secondary btn-sm" data-action-url="{{ route('precios.compra.validar', $i) }}"
                                                data-confirm="¿Validar los precios de {{ $i->codigo }}?" data-text="Confirma que el último precio ya se refleja en las facturas de Solgas.">Validar</button>
                                    @endif
                                @endif
                                @if ($modo === 'instalaciones')
                                    <x-row-actions :show="route('instalaciones.show', $i)" :edit="route('instalaciones.edit', $i)" :delete="route('instalaciones.destroy', $i)"/>
                                @else
                                    <x-row-actions :show="route('instalaciones.show', $i)"/>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card"><x-empty title="Sin instalaciones" text="Registre la primera instalación con su código de 8 dígitos."/></div>
@endforelse
