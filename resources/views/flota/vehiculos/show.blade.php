<x-modal :title="'Vehículo '.$vehiculo->placa" :subtitle="trim((\App\Models\Vehiculo::TIPOS[$vehiculo->tipo] ?? '').' '.$vehiculo->marca.' '.$vehiculo->modelo)" icon="truck">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'documentos' => 'Documentos ('.$vehiculo->documentos->count().')', 'mantenimientos' => 'Mantenimientos ('.$vehiculo->mantenimientos->count().')', 'actividad' => 'Actividad', 'historial' => 'Historial']"/>

        <div x-show="tab === 'detalle'" class="space-y-6">
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($vehiculo->estadoDocumentos() as $tipo => $info)
                    <div @class(['kpi border-l-[3px]',
                        'border-l-emerald-600' => $info['estado'] === 'vigente',
                        'border-l-amber-500' => $info['estado'] === 'por_vencer',
                        'border-l-red-600' => $info['estado'] === 'vencido',
                        'border-l-slate-300' => in_array($info['estado'], ['sin_registro', 'sin_fecha'])])>
                        <div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $info['label'] }}</p><x-status :value="$info['estado']"/></div>
                        @if ($info['documento'])
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ $info['documento']->fecha_vencimiento?->format('d/m/Y') ?? 'Sin fecha' }}</p>
                            @if (! is_null($info['documento']->diasParaVencer()))
                                <p class="text-xs text-slate-500">{{ $info['documento']->diasParaVencer() >= 0 ? 'Vence en '.$info['documento']->diasParaVencer().' días' : 'Venció hace '.abs($info['documento']->diasParaVencer()).' días' }}</p>
                            @endif
                        @else
                            <button class="mt-2 text-xs font-semibold text-brand-600 hover:underline" data-modal-url="{{ route('documentos.create', [$vehiculo, 'tipo' => $tipo]) }}" data-modal-size="md">+ Registrar</button>
                        @endif
                    </div>
                @endforeach
            </div>
            <dl class="dl-grid">
                <div><dt>Placa</dt><dd>{{ $vehiculo->placa }}</dd></div>
                <div><dt>Año</dt><dd>{{ $vehiculo->anio ?: '—' }}</dd></div>
                <div><dt>Color</dt><dd>{{ $vehiculo->color ?: '—' }}</dd></div>
                <div><dt>Capacidad</dt><dd>{{ $vehiculo->capacidad_balones ? $vehiculo->capacidad_balones.' balones' : '—' }}</dd></div>
                <div><dt>Kilometraje</dt><dd>{{ $vehiculo->kilometraje ? num($vehiculo->kilometraje).' km' : '—' }}</dd></div>
                <div><dt>Empresa</dt><dd>{{ $vehiculo->empresa?->nombre ?? '—' }}</dd></div>
                <div><dt>Choferes asignados</dt><dd>{{ $vehiculo->choferes->pluck('alias')->join(', ') ?: '—' }}</dd></div>
                <div><dt>Estado</dt><dd><x-status :value="$vehiculo->estado"/></dd></div>
                <div class="col-span-full"><dt>Observaciones</dt><dd>{{ $vehiculo->observaciones ?: '—' }}</dd></div>
            </dl>
        </div>

        <div x-show="tab === 'documentos'" x-cloak>
            <div class="mb-3 flex justify-end">
                <button class="btn btn-primary btn-sm" data-modal-url="{{ route('documentos.create', $vehiculo) }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Agregar documento</button>
            </div>
            <table class="table table-compact">
                <thead><tr><th>Documento</th><th>Número</th><th>Emisión</th><th>Vencimiento</th><th>Estado</th><th>Costo</th><th></th></tr></thead>
                <tbody>
                @forelse ($vehiculo->documentos as $d)
                    <tr>
                        <td class="font-medium">{{ $d->tipo->label() }}</td>
                        <td class="font-mono text-xs">{{ $d->numero ?: '—' }}</td>
                        <td>{{ fecha($d->fecha_emision) ?: '—' }}</td>
                        <td>{{ fecha($d->fecha_vencimiento) ?: '—' }}</td>
                        <td><x-status :value="$d->estadoVencimiento()"/></td>
                        <td>{{ $d->costo ? soles($d->costo) : '—' }}</td>
                        <td>
                            <x-row-actions size="md" :show="route('documentos.show', $d)" :edit="route('documentos.edit', $d)" :delete="route('documentos.destroy', $d)">
                                @if ($d->archivo)
                                    <a href="{{ route('documentos.archivo', $d) }}" target="_blank" class="btn-icon info" title="Ver archivo"><x-heroicon-o-paper-clip class="h-4 w-4"/></a>
                                @endif
                            </x-row-actions>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-400">Sin documentos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'mantenimientos'" x-cloak>
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm text-slate-500">Gasto total: <b class="text-slate-900">{{ soles($vehiculo->mantenimientos->sum('costo')) }}</b></p>
                <button class="btn btn-primary btn-sm" data-modal-url="{{ route('mantenimientos.create', $vehiculo) }}" data-modal-size="md"><x-heroicon-o-plus class="h-4 w-4"/> Registrar mantenimiento</button>
            </div>
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Km</th><th>Costo</th><th>Próximo</th><th></th></tr></thead>
                <tbody>
                @forelse ($vehiculo->mantenimientos as $m)
                    <tr>
                        <td>{{ fecha($m->fecha) }}</td>
                        <td><x-badge color="blue">{{ \App\Models\VehiculoMantenimiento::TIPOS[$m->tipo] ?? $m->tipo }}</x-badge></td>
                        <td>{{ $m->descripcion }}<p class="text-xs text-slate-400">{{ $m->taller }}</p></td>
                        <td>{{ $m->kilometraje ? num($m->kilometraje) : '—' }}</td>
                        <td>{{ soles($m->costo) }}</td>
                        <td class="text-xs">{{ fecha($m->proximo_fecha) }} {{ $m->proximo_kilometraje ? '· '.num($m->proximo_kilometraje).' km' : '' }}</td>
                        <td><x-row-actions size="md" :show="route('mantenimientos.show', $m)" :edit="route('mantenimientos.edit', $m)" :delete="route('mantenimientos.destroy', $m)"/></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-400">Sin mantenimientos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'actividad'" x-cloak>
            <p class="mb-2 text-sm font-semibold text-slate-900">Últimos movimientos en el parte diario</p>
            @include('logistica.partes._movimientos')
        </div>

        <div x-show="tab === 'historial'" x-cloak><x-history :model="$vehiculo"/></div>
    </div>
</x-modal>
