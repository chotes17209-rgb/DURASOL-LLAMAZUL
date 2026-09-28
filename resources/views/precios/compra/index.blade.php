<x-layouts.app title="Precios de compra en planta" breadcrumb="Precios">
    <x-slot:actions>
        <a href="{{ route('precios.compra.historial') }}" class="btn btn-secondary"><x-heroicon-o-clock class="h-4 w-4"/> Historial</a>
    </x-slot:actions>
    <div class="mb-5 rounded-2xl bg-brand-50 p-4 text-sm text-brand-900 ring-1 ring-brand-100">
        Precio al que cada empresa compra en la planta de Solgas, <b>por instalación</b>. Al registrar una guía, el sistema toma automáticamente el precio vigente de su instalación.
    </div>
    <div class="space-y-6">
        @foreach ($empresas as $empresa)
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full" style="background: {{ $empresa->color }}"></span>
                        <p class="card-title text-base">{{ $empresa->nombre }}</p>
                        <x-badge color="slate">{{ $empresa->instalaciones->count() }} instalaciones</x-badge>
                    </div>
                </div>
                @if ($empresa->instalaciones->isEmpty())
                    <x-empty title="Sin instalaciones" text="Registra primero las instalaciones de esta empresa."/>
                @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Instalación</th><th>Chofer / camión</th>
                            @foreach ($productos as $p)<th class="text-right">{{ $p->codigo }}</th>@endforeach<th></th></tr></thead>
                        <tbody>
                        @foreach ($empresa->instalaciones as $i)
                            <tr>
                                <td><p class="font-mono font-bold">{{ $i->codigo }}</p><p class="text-xs text-slate-500">{{ $i->nombre }}</p></td>
                                <td class="text-xs">{{ $i->chofer?->alias ?? '—' }} · {{ $i->vehiculo?->placa ?? '—' }}</td>
                                @foreach ($productos as $p)
                                    @php($precio = $vigentes[$i->id][$p->id] ?? null)
                                    <td class="text-right tabular-nums">
                                        @if ($precio)
                                            <span class="font-semibold text-slate-900">{{ num($precio->precio, 2) }}</span>
                                            <p class="text-[10px] text-slate-400">{{ fecha($precio->vigente_desde) }}</p>
                                        @else — @endif
                                    </td>
                                @endforeach
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <button class="btn-icon info" title="Ver instalación e historial" data-modal-url="{{ route('instalaciones.show', $i) }}"><x-heroicon-o-eye class="h-4 w-4"/></button>
                                        @if (auth()->user()->isAdmin())
                                            <button class="btn btn-primary btn-sm" data-modal-url="{{ route('precios.compra.create', ['instalacion_id' => $i->id]) }}" data-modal-size="lg"><x-heroicon-o-pencil-square class="h-4 w-4"/> Actualizar</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.app>
