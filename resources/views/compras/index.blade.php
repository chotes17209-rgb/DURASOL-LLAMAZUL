@php
    $c = $cuadro;
    $nombres = ['S10' => 'Solgas S10', 'S45' => 'Solgas S45', 'M10' => 'Masgas S10'];
    // En Perú se escribe "setiembre", como en la hoja de compras.
    $mesAnt = str_replace('Septiembre', 'Setiembre', ucfirst($c['anterior']->translatedFormat('F')));
    $mesAct = str_replace('Septiembre', 'Setiembre', ucfirst($c['mes']->translatedFormat('F')));
    $titulo = $empresa?->nombre ?? 'Global';
    $tema = $empresa ? (str_contains(mb_strtolower($empresa->nombre), 'llama') ? 'llamazul' : 'durasol') : 'global';
    $hoy = today();
    // Gráfico base 10 kg como el del Excel: el eje no empieza en cero, así se nota la diferencia entre meses.
    [$b1, $b2] = [$c['base10']['anterior'], $c['base10']['actual']];
    $maxBase = max(1, $b1, $b2);
    $piso = $b1 && $b2 ? max(0, (int) (floor((min($b1, $b2) - 0.8 * abs($b1 - $b2)) / 100) * 100)) : 0;
    $alto = fn ($v) => $maxBase > $piso ? round(max(0, $v - $piso) / ($maxBase - $piso) * 100, 1) : 100;
@endphp
<x-layouts.app title="Compras en planta" breadcrumb="Compras y precios">
    <x-slot:actions>
        <x-export :url="route('compras.index', ['empresa' => $vista, 'mes' => $c['mes']->format('Y-m')])"/>
        <button class="btn btn-secondary" data-modal-url="{{ route('compras.cuotas', ['mes' => $c['mes']->format('Y-m')]) }}" data-modal-size="md"><x-heroicon-o-flag/> Cuotas del mes</button>
        <button class="btn btn-primary" data-modal-url="{{ route('compras.create', ['empresa' => $vista, 'fecha' => $c['mes']->isSameMonth($hoy) ? $hoy->toDateString() : $c['mes']->copy()->endOfMonth()->toDateString()]) }}" data-modal-size="md"><x-heroicon-o-plus/> Registrar compra</button>
    </x-slot:actions>
    <x-slot:filters>
        <form method="GET">
            <div class="fb">
                <label>Empresa</label>
                <select name="empresa" class="form-input w-48" onchange="this.form.submit()">
                    @foreach ($empresas as $e)<option value="{{ $e->id }}" @selected($vista === (string) $e->id)>{{ $e->nombre }}</option>@endforeach
                    <option value="global" @selected($vista === 'global')>Global (todas)</option>
                </select>
            </div>
            <div class="fb">
                <label>Mes</label>
                <input type="month" name="mes" value="{{ $c['mes']->format('Y-m') }}" class="form-input w-56" onchange="this.form.submit()">
            </div>
            <button class="btn btn-primary">Aplicar</button>
        </form>
        <p class="ml-auto self-center text-[12.5px] text-[#556b82]">{{ ucfirst($c['mes']->translatedFormat('F Y')) }} · {{ $c['dias_con_compra'] }} días con compra</p>
    </x-slot:filters>


    {{-- Cuadro con el formato de la hoja de compras del Excel (DURASOL / LLAMAZUL / GLOBAL) --}}
    <div class="xl-hoja xl-{{ $tema }}">
        <div class="grid gap-x-10 gap-y-6 lg:grid-cols-[minmax(0,25rem)_minmax(0,1fr)]">
            <div class="overflow-x-auto">
                <table class="xl-tabla w-full">
                    <thead>
                    <tr><th class="xl-blanco"></th><th colspan="2" class="xl-gris-osc">SOLGAS</th><th class="xl-blanco">MAS GAS</th></tr>
                    <tr><th class="xl-blanco">FECHA</th><th class="xl-gris">S10</th><th class="xl-durazno">S45</th><th class="xl-celeste">S10</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($c['dias'] as $d)
                        <tr>
                            <td class="text-center">{{ $d['fecha']->format('j/m/Y') }}</td>
                            @foreach (['S10', 'S45', 'M10'] as $k)
                                <td class="text-right">{{ ($d['cantidades'][$k] ?? 0) ? number_format($d['cantidades'][$k], 2) : '-' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="xl-total">
                        <td class="text-center">TOTAL</td>
                        @foreach (['S10', 'S45', 'M10'] as $k)<td class="text-right">{{ $c['total'][$k] ? number_format($c['total'][$k], 2) : '-' }}</td>@endforeach
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="min-w-0 space-y-5">
                <p class="xl-banda">CUOTA DEL MES</p>
                <div class="grid gap-x-10 gap-y-5 sm:grid-cols-2">
                    @foreach (['S10' => 'SOLGAS - S10', 'M10' => 'MAS GAS - S10', 'S45' => 'SOLGAS - S45'] as $k => $rotulo)
                        @php($q = $c['cuota'][$k])
                        <table class="xl-cuota {{ $k === 'M10' ? 'xl-cuota-masgas' : '' }}">
                            <tr><th colspan="2">{{ $rotulo }}</th><td class="xl-sin-borde"></td></tr>
                            <tr><td colspan="2" class="xl-meta">{{ $q['cuota'] ? num($q['cuota']) : '—' }}</td><td class="xl-pct">{{ $q['porcentaje'] !== null ? number_format($q['porcentaje'], 2).'%' : '—' }}</td></tr>
                            <tr><td class="xl-avance">AVANCE</td><td class="text-center font-semibold">{{ num($q['avance']) }}</td><td class="xl-sin-borde"></td></tr>
                            <tr><td class="xl-dif">DIFERENCIA</td><td class="xl-dif-valor {{ $q['diferencia'] < 0 ? 'text-[#c00000]' : '' }}">{{ $q['cuota'] ? num($q['diferencia']) : '0' }}</td><td class="xl-sin-borde"></td></tr>
                        </table>
                    @endforeach
                    <div class="flex items-center justify-center">
                        @if ($tema === 'durasol')
                            <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-16 w-auto">
                        @elseif ($tema === 'llamazul')
                            <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-12 w-auto">
                        @endif
                    </div>
                </div>

                <p class="xl-banda">COMPARATIVA MES ANTERIOR X DIA</p>
                <div class="overflow-x-auto">
                    <table class="xl-tabla xl-comparativa">
                        <thead><tr><th class="xl-blanco">PROD.</th><th class="xl-durazno">{{ mb_strtoupper($mesAnt) }}</th><th class="xl-blanco">{{ mb_strtoupper($mesAct) }}</th><th class="xl-blanco">SUBE/BAJA</th><th class="xl-blanco">%</th></tr></thead>
                        <tbody>
                        @foreach ($c['comparativa'] as $k => $q)
                            <tr @class(['xl-tot' => $k === 'TOTAL'])>
                                <td class="text-center font-semibold">{{ $k === 'TOTAL' ? 'TOT (10kg)' : substr($k, 0, 1).'-'.substr($k, 1) }}</td>
                                <td class="xl-durazno-claro text-center font-semibold">{{ num($q['anterior']) }}</td>
                                <td class="text-center">{{ num($q['actual']) }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="flex items-center justify-between gap-2">
                                        @if ($q['variacion'] > 0)
                                            <svg viewBox="0 0 12 14" class="h-3.5 w-3 fill-[#00b050]"><path d="M6 0 12 6.5H8.5V14h-5V6.5H0z"/></svg>
                                        @elseif ($q['variacion'] < 0)
                                            <svg viewBox="0 0 12 14" class="h-3.5 w-3 fill-[#ed7d31]"><path d="M6 14 0 7.5h3.5V0h5v7.5H12z"/></svg>
                                        @else
                                            <span class="inline-block h-1.5 w-4 bg-[#ffc000]"></span>
                                        @endif
                                        <span>{{ $q['variacion'] < 0 ? '-' : '' }}{{ num(abs($q['variacion'])) }}</span>
                                    </span>
                                </td>
                                <td class="text-right">{{ $q['porcentaje'] !== null ? number_format($q['porcentaje'], 2).'%' : '--' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="xl-grafico">
                    <p class="xl-grafico-titulo">{{ $tema === 'global' ? 'RESUMEN BASE 10KG - GLOBAL' : 'COMPARATIVA A BASE 10 KG - '.mb_strtoupper($titulo) }}</p>
                    <div class="xl-barras">
                        @foreach ([[$mesAnt, $c['base10']['anterior'], '#f8cbad'], [$mesAct, $c['base10']['actual'], '#d9d9d9']] as [$mes, $valor, $color])
                            <div class="xl-barra">
                                <div class="xl-barra-area">
                                    <div class="xl-barra-col" style="height: {{ $alto($valor) }}%; background: {{ $color }}"><span>{{ num($valor) }}</span></div>
                                </div>
                                <p>{{ mb_strtoupper($mes) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="card mt-4">
        <div class="card-header">
            <p class="card-title">Registro de compras · {{ $titulo }}</p>
            <span class="text-[12px] text-slate-500">{{ $registros->count() }} registro(s)@can('ver-precios-compra') · importe {{ soles($registros->sum(fn ($r) => $r->importe() ?? 0)) }}@endcan</span>
        </div>
        <div class="table-wrap max-h-[32rem]">
            <table class="table table-compact">
                <thead class="sticky top-0 z-[1]"><tr><th>Fecha</th><th>Empresa</th><th>Producto</th><th>Instalación</th><th class="text-right">Cantidad</th>@can('ver-precios-compra')<th class="text-right">Precio unit.</th><th class="text-right">Importe</th>@endcan<th>Guía / factura</th><th>Origen</th><th class="w-20"></th></tr></thead>
                <tbody>
                @forelse ($registros as $r)
                    <tr>
                        <td class="whitespace-nowrap">{{ fecha($r->fecha) }}</td>
                        <td class="font-medium text-slate-900">{{ $r->empresa?->nombre }}</td>
                        <td>{{ $r->producto?->codigo }}</td>
                        <td class="text-[12px] text-slate-600">{{ $r->instalacion ? $r->instalacion->codigo.' · '.($r->instalacion->responsable ?? '') : '—' }}</td>
                        <td class="text-right font-semibold">{{ num($r->cantidad) }}</td>
                        @can('ver-precios-compra')
                            <td class="text-right">{{ $r->precio_unitario !== null ? num($r->precio_unitario, 2) : '—' }}</td>
                            <td class="text-right">{{ $r->importe() !== null ? num($r->importe(), 2) : '—' }}</td>
                        @endcan
                        <td class="text-[12px]">{{ $r->documento }}</td>
                        <td><span class="text-[12px] text-slate-600">{{ \App\Models\CompraPlanta::ORIGENES[$r->origen] ?? $r->origen }}</span></td>
                        <td>@if ($r->editable())<x-row-actions size="md" :edit="route('compras.edit', $r)" :delete="route('compras.destroy', $r)"/>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="10"><x-empty text="No hay compras registradas en el mes."/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
