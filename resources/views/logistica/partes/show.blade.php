@php
    $llenos = \App\Models\ParteFila::COLUMNAS_LLENOS;
    $vacios = \App\Models\ParteFila::COLUMNAS_VACIOS;
@endphp
<x-layouts.app title="Parte diario de almacén"  breadcrumb="Parte diario">
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
        <section class="doc-head mb-4">
            <div class="doc-band">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-[10.5px] font-semibold tracking-[.16em] text-[#b9c7df] uppercase">Parte diario de almacén</p>
                        <p class="doc-num">{{ $dia->format('d/m/Y') }}</p>
                    </div>
                    <span class="doc-tag">{{ ! $parte->exists ? 'Nuevo' : ($parte->esEditable() ? 'Abierto' : 'Cerrado') }}</span>
                </div>
                <div class="text-right text-[12px] leading-snug text-[#d4ddec]">
                    <p class="font-semibold text-white">{{ ucfirst($dia->translatedFormat('l d \\d\\e F \\d\\e Y')) }}</p>
                    @if ($parte->exists && ! $parte->esEditable())
                        <p>Cerrado{{ $parte->cerradoPor ? ' por '.$parte->cerradoPor->name : '' }}{{ $parte->cerrado_at ? ' el '.$parte->cerrado_at->format('d/m/Y H:i') : '' }}</p>
                    @else
                        <p>Stock inicial = stock final del día anterior</p>
                    @endif
                </div>
            </div>
            <dl class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6">
                <template x-for="[p, titulo] in [['s10', 'Total S-10'], ['s45', 'Total S-45'], ['m10', 'Total M-10']]" :key="p">
                    <div class="ledger-cell">
                        <dt x-text="titulo"></dt>
                        <dd x-text="n(totalPresentacion(p))"></dd>
                        <p class="text-[11px] text-slate-500"><span x-text="'llenos ' + n(control('lleno_' + p).final)"></span> + <span x-text="'cambios ' + n(control('cambio_' + p).final)"></span></p>
                    </div>
                </template>
                <template x-for="[p, titulo] in [['s10', 'Vacíos S-10'], ['s45', 'Vacíos S-45']]" :key="p">
                    <div class="ledger-cell">
                        <dt x-text="titulo"></dt>
                        <dd x-text="n(totalVacios(p))"></dd>
                        <p class="text-[11px] text-slate-500"><span x-text="'plomos ' + n(control('plomo_' + p).final)"></span> + <span x-text="'colores ' + n(control('color_' + p).final)"></span></p>
                    </div>
                </template>
                <template x-for="[llave, titulo] in [['cambio_s10', 'Cambios (fallados) S-10']]" :key="llave">
                    <div class="ledger-cell">
                        <dt x-text="titulo"></dt>
                        <dd :class="control(llave).final < 0 && '!text-red-700'" x-text="n(control(llave).final)"></dd>
                        <p class="text-[11px] text-slate-500"><span x-text="'Inicial ' + n(control(llave).inicial)"></span> · <span class="text-emerald-700" x-text="'+' + n(control(llave).ingreso)"></span> · <span class="text-red-700" x-text="'−' + n(control(llave).salida)"></span></p>
                    </div>
                </template>
            </dl>
        </section>
        <p class="help mb-3" x-show="editable">Escribe las cantidades como en la hoja de logística; las filas vacías no se guardan. <b>Enter</b> baja a la fila siguiente. Al escribir la placa de un camión de planta se propone su instalación.</p>

        <div class="tabs mb-4">
            <button type="button" class="tab" :class="tab === 'llenos' && 'active'" @click="tab = 'llenos'">Llenos</button>
            <button type="button" class="tab" :class="tab === 'vacios' && 'active'" @click="tab = 'vacios'">Vacíos</button>
            <button type="button" class="tab" :class="tab === 'masa' && 'active'" @click="tab = 'masa'">Control de masa (planta)</button>
            <button type="button" class="tab" :class="tab === 'cuadre' && 'active'" @click="tab = 'cuadre'">Cuadre con ventas</button>
        </div>

        <div x-show="tab === 'llenos'" class="space-y-4">
            @include('logistica.partes._bloque', ['bloque' => 'lleno_ingreso', 'titulo' => 'INGRESO DE LLENOS — planta, retornos de choferes y cambios', 'columnas' => $llenos, 'conPlanta' => true])
            @include('logistica.partes._bloque', ['bloque' => 'lleno_salida', 'titulo' => 'SALIDA DE LLENOS — choferes, local y clientes de ruta', 'columnas' => $llenos, 'conPlanta' => false])
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
            @include('logistica.partes._control', ['titulo' => 'CONTROL DE STOCK LLENOS', 'llaves' => ['lleno_s10' => 'S-10', 'lleno_s45' => 'S-45', 'lleno_m10' => 'M-10', 'cambio_s10' => 'Cambio S-10', 'cambio_s45' => 'Cambio S-45', 'cambio_m10' => 'Cambio M-10']])
                <div class="card">
                    <div class="card-header"><p class="card-title">Total</p><span class="text-[11px] text-slate-500">llenos + cambios</span></div>
                    <table class="table table-grid">
                        <thead><tr><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">M-10</th></tr></thead>
                        <tbody><tr><template x-for="p in ['s10', 's45', 'm10']" :key="p"><td class="text-right text-[18px] font-semibold text-brand-950" x-text="n(totalPresentacion(p))"></td></template></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div x-show="tab === 'vacios'" class="space-y-4">
            @include('logistica.partes._bloque', ['bloque' => 'vacio_ingreso', 'titulo' => 'INGRESO DE VACÍOS — choferes, clientes y canje', 'columnas' => $vacios, 'conPlanta' => false])
            @include('logistica.partes._bloque', ['bloque' => 'vacio_salida', 'titulo' => 'SALIDA DE VACÍOS — a planta y canje', 'columnas' => $vacios, 'conPlanta' => true])
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                @include('logistica.partes._control', ['titulo' => 'CONTROL DE STOCK VACÍOS', 'llaves' => ['plomo_s10' => 'Plomos S-10', 'plomo_s45' => 'Plomos S-45', 'color_s10' => 'Colores S-10', 'color_s45' => 'Colores S-45']])
                <div class="card">
                    <div class="card-header"><p class="card-title">Total vacíos</p><span class="text-[11px] text-slate-500">plomos + colores</span></div>
                    <table class="table table-grid">
                        <thead><tr><th class="text-right">S-10</th><th class="text-right">S-45</th></tr></thead>
                        <tbody><tr><template x-for="p in ['s10', 's45']" :key="p"><td class="text-right text-[18px] font-semibold text-brand-950" x-text="n(totalVacios(p))"></td></template></tr></tbody>
                    </table>
                </div>
            </div>
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
                    Total S-10: <b class="text-slate-800" x-text="n(totalPresentacion('s10'))"></b> ·
                    S-45: <b class="text-slate-800" x-text="n(totalPresentacion('s45'))"></b> ·
                    M-10: <b class="text-slate-800" x-text="n(totalPresentacion('m10'))"></b> ·
                    Vacíos S-10: <b class="text-slate-800" x-text="n(totalVacios('s10'))"></b> · S-45: <b class="text-slate-800" x-text="n(totalVacios('s45'))"></b>
                    <span class="ml-2 text-amber-700" x-show="sucio">· Cambios sin guardar</span>
                </p>
                <button type="button" class="btn btn-primary" @click="guardar()" :disabled="guardando"><span x-text="guardando ? 'Guardando...' : 'Guardar parte'"></span></button>
            </div>
        </div>
    </div>
</x-layouts.app>
