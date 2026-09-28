@php
    $llenos = \App\Models\ParteFila::COLUMNAS_LLENOS;
    $vacios = \App\Models\ParteFila::COLUMNAS_VACIOS;
@endphp
<x-layouts.app title="Parte diario de almacén" :breadcrumb="'Logística · '.ucfirst($dia->translatedFormat('l d \\d\\e F \\d\\e Y'))">
    <x-slot:actions>
        <div class="flex items-center">
            @if ($anterior)
                <a href="{{ route('logistica.partes.show', \Illuminate\Support\Carbon::parse($anterior)->toDateString()) }}" class="btn btn-secondary rounded-r-none" title="Parte anterior"><x-heroicon-o-chevron-left/></a>
            @endif
            <form method="GET" action="{{ route('logistica.partes.abrir') }}">
                <input type="date" name="fecha" value="{{ $dia->toDateString() }}" class="form-input w-36 rounded-none" onchange="this.form.submit()">
            </form>
            @if ($siguiente)
                <a href="{{ route('logistica.partes.show', \Illuminate\Support\Carbon::parse($siguiente)->toDateString()) }}" class="btn btn-secondary rounded-l-none" title="Parte siguiente"><x-heroicon-o-chevron-right/></a>
            @endif
        </div>
        @if ($parte->exists)
            <a href="{{ route('logistica.partes.reporte', [$dia->toDateString(), 'formato' => 'pdf']) }}" class="btn btn-secondary"><x-heroicon-o-document-arrow-down/> PDF</a>
            <a href="{{ route('logistica.partes.reporte', [$dia->toDateString(), 'formato' => 'xlsx']) }}" class="btn btn-secondary"><x-heroicon-o-table-cells/> Excel</a>
        @endif
        @if ($parte->esEditable())
            <button class="btn btn-secondary" data-modal-url="{{ route('logistica.partes.ajuste', $dia->toDateString()) }}" data-modal-size="md">Ajustar a conteo</button>
            @if ($parte->exists)
                <button class="btn btn-secondary" data-action-url="{{ route('logistica.partes.cerrar', $dia->toDateString()) }}" data-confirm="¿Cerrar el parte del {{ $dia->format('d/m/Y') }}?" data-text="Después no se podrá modificar.">Cerrar parte</button>
            @endif
        @elseif (auth()->user()->isAdmin())
            <button class="btn btn-warning" data-action-url="{{ route('logistica.partes.reabrir', $dia->toDateString()) }}" data-confirm="¿Reabrir el parte?">Reabrir</button>
        @endif
    </x-slot:actions>

    <datalist id="lista-choferes">@foreach ($choferes as $c)<option value="{{ $c }}">@endforeach</datalist>
    <datalist id="lista-placas">@foreach ($placas as $p)<option value="{{ $p }}">@endforeach</datalist>
    <datalist id="lista-lugares">@foreach (\App\Models\ParteFila::LUGARES as $l)<option value="{{ $l }}">@endforeach</datalist>

    <div x-data="parteEditor(@js($config))" x-cloak class="pb-16">
        <div class="mb-3 flex flex-wrap items-center gap-3">
            @if (! $parte->exists)
                <span class="badge badge-amber">Nuevo: aún no se ha guardado</span>
            @elseif ($parte->esEditable())
                <span class="badge badge-green">Abierto</span>
            @else
                <span class="badge badge-slate">Cerrado{{ $parte->cerradoPor ? ' por '.$parte->cerradoPor->name : '' }}{{ $parte->cerrado_at ? ' el '.$parte->cerrado_at->format('d/m/Y H:i') : '' }}</span>
            @endif
            <p class="text-xs text-slate-500" x-show="editable">El stock inicial es el final del día anterior. Escribe las cantidades como en la hoja; las filas vacías no se guardan. <b>Enter</b> baja a la siguiente fila.</p>
        </div>

        <div class="tabs mb-4">
            <button type="button" class="tab" :class="tab === 'llenos' && 'active'" @click="tab = 'llenos'">Llenos</button>
            <button type="button" class="tab" :class="tab === 'vacios' && 'active'" @click="tab = 'vacios'">Vacíos</button>
            <button type="button" class="tab" :class="tab === 'masa' && 'active'" @click="tab = 'masa'">Control de masa (planta)</button>
            <button type="button" class="tab" :class="tab === 'cuadre' && 'active'" @click="tab = 'cuadre'">Cuadre con ventas</button>
        </div>

        <div x-show="tab === 'llenos'" class="space-y-4">
            @include('logistica.partes._bloque', ['bloque' => 'lleno_ingreso', 'titulo' => 'INGRESO DE LLENOS — planta, retornos de choferes y cambios', 'columnas' => $llenos, 'conPlanta' => true])
            @include('logistica.partes._bloque', ['bloque' => 'lleno_salida', 'titulo' => 'SALIDA DE LLENOS — choferes, local y clientes de ruta', 'columnas' => $llenos, 'conPlanta' => false])
            @include('logistica.partes._control', ['titulo' => 'CONTROL DE STOCK LLENOS', 'llaves' => ['lleno_s10' => 'S-10', 'lleno_s45' => 'S-45', 'lleno_m10' => 'M-10', 'cambio_s10' => 'Cambio S-10', 'cambio_s45' => 'Cambio S-45', 'cambio_m10' => 'Cambio M-10']])
        </div>

        <div x-show="tab === 'vacios'" class="space-y-4">
            @include('logistica.partes._bloque', ['bloque' => 'vacio_ingreso', 'titulo' => 'INGRESO DE VACÍOS — choferes, clientes y canje', 'columnas' => $vacios, 'conPlanta' => false])
            @include('logistica.partes._bloque', ['bloque' => 'vacio_salida', 'titulo' => 'SALIDA DE VACÍOS — a planta y canje', 'columnas' => $vacios, 'conPlanta' => true])
            @include('logistica.partes._control', ['titulo' => 'CONTROL DE STOCK VACÍOS', 'llaves' => ['plomo_s10' => 'Plomo S-10', 'plomo_s45' => 'Plomo S-45', 'color_s10' => 'Color S-10', 'color_s45' => 'Color S-45']])
        </div>

        <div x-show="tab === 'masa'" class="card">
            <div class="card-header"><p class="card-title">Control de masa con la planta (según lo guardado)</p></div>
            <p class="px-4 pt-3 text-xs text-slate-500">Por placa: los vacíos que salieron a planta deben regresar como llenos. Se toman las filas con lugar <b>PLANTA</b>.</p>
            <div class="table-wrap p-4">
                <table class="table table-compact table-grid">
                    <thead><tr><th>Placa</th><th>Responsable</th><th>Empresa</th><th>Guías</th><th class="text-right">Vacíos a planta</th><th class="text-right">Llenos de planta</th><th class="text-right">Diferencia</th></tr></thead>
                    <tbody>
                    @forelse ($masa as $m)
                        <tr>
                            <td class="font-mono font-semibold">{{ $m['placa'] }}</td><td>{{ $m['responsable'] }}</td><td>{{ $m['empresa'] }}</td><td class="text-xs">{{ $m['guias'] }}</td>
                            <td class="text-right">{{ num($m['vacios']) }}</td><td class="text-right">{{ num($m['llenos']) }}</td>
                            <td class="text-right font-semibold {{ $m['diferencia'] === 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $m['diferencia'] === 0 ? 'Cuadra' : ($m['diferencia'] > 0 ? '+' : '').num($m['diferencia']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-400">Sin movimientos con planta en este parte.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'cuadre'" class="card">
            <div class="card-header"><p class="card-title">Cuadre de choferes con sus liquidaciones del {{ $dia->format('d/m/Y') }}</p></div>
            <p class="px-4 pt-3 text-xs text-slate-500">Vendido según almacén = salida de llenos − llenos que devolvió. Debe coincidir con lo registrado en su liquidación.</p>
            <div class="table-wrap p-4">
                <table class="table table-compact table-grid">
                    <thead>
                    <tr><th rowspan="2">Chofer</th>@foreach (['S10', 'S45', 'M10'] as $p)<th colspan="4" class="text-center">{{ $p }}</th>@endforeach</tr>
                    <tr>@foreach (['S10', 'S45', 'M10'] as $p)<th class="text-right">Salió</th><th class="text-right">Volvió</th><th class="text-right">Vendido</th><th class="text-right">Liquidado</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @forelse ($cuadre as $c)
                        <tr>
                            <td class="font-semibold">{{ $c['chofer']->alias }}</td>
                            @foreach ($c['productos'] as $p)
                                <td class="text-right">{{ $p['salio'] ?: '' }}</td><td class="text-right">{{ $p['volvio'] ?: '' }}</td>
                                <td class="text-right font-semibold">{{ $p['vendido'] ?: '' }}</td>
                                <td class="text-right {{ $p['vendido'] !== $p['liquidado'] ? 'bg-red-50 font-semibold text-red-700' : '' }}">{{ $p['liquidado'] ?: '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="13" class="py-6 text-center text-slate-400">Sin salidas de choferes ni liquidaciones en esta fecha.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><p class="card-title">Observaciones del día</p></div>
            <div class="p-3"><textarea class="form-input" rows="2" x-model="observaciones" :disabled="!editable"></textarea></div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-white px-6 py-2.5 lg:left-64 no-print" style="box-shadow: 0 -4px 12px -6px rgb(10 26 56 / .15)" x-show="editable">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Llenos S-10 final: <b class="text-slate-800" x-text="n(control('lleno_s10').final)"></b> ·
                    S-45: <b class="text-slate-800" x-text="n(control('lleno_s45').final)"></b> ·
                    M-10: <b class="text-slate-800" x-text="n(control('lleno_m10').final)"></b> ·
                    Vacíos S-10 (plomo + color): <b class="text-slate-800" x-text="n(control('plomo_s10').final + control('color_s10').final)"></b>
                    <span class="ml-2 text-amber-700" x-show="sucio">· Cambios sin guardar</span>
                </p>
                <button type="button" class="btn btn-primary" @click="guardar()" :disabled="guardando"><span x-text="guardando ? 'Guardando...' : 'Guardar parte'"></span></button>
            </div>
        </div>
    </div>
</x-layouts.app>
