@php
    $c = $cuadro;
    $nombres = ['S10' => 'Solgas S10', 'S45' => 'Solgas S45', 'M10' => 'Masgas S10'];
    $mesAnt = ucfirst($c['anterior']->translatedFormat('F'));
    $mesAct = ucfirst($c['mes']->translatedFormat('F'));
    $titulo = $empresa?->nombre ?? 'Global';
    $hoy = today();
    $diasMes = $c['mes']->daysInMonth;
    $transcurridos = $c['mes']->isSameMonth($hoy) ? $hoy->day : ($c['mes']->lt($hoy) ? $diasMes : 0);
    $acumulado = 0;
    $serie = $c['dias']->map(function ($d) use (&$acumulado) { $acumulado += $d['cantidades']['S10'] ?? 0; return $acumulado; });
    $grafico = [
        'type' => 'bar',
        'data' => ['labels' => [$mesAnt, $mesAct], 'datasets' => [['label' => 'Base 10 kg', 'data' => [$c['base10']['anterior'], $c['base10']['actual']],
            'backgroundColor' => ['#f6c9a4', '#1f3f95'], 'borderRadius' => 6, 'maxBarThickness' => 90]]],
        'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef0f3']]]],
    ];
    $avance = [
        'type' => 'line',
        'data' => ['labels' => $c['dias']->map(fn ($d) => $d['fecha']->format('d'))->values(), 'datasets' => array_values(array_filter([
            ['label' => 'Compras acumuladas S10', 'data' => $serie->values(), 'borderColor' => '#1f3f95', 'backgroundColor' => 'rgba(31,63,149,.08)', 'fill' => true, 'tension' => 0.25, 'pointRadius' => 0, 'borderWidth' => 2],
            $c['cuota']['S10']['cuota'] ? ['label' => 'Cuota S10', 'data' => $c['dias']->map(fn ($d, $i) => round($c['cuota']['S10']['cuota'] * ($i + 1) / $diasMes))->values(),
                'borderColor' => '#d9661a', 'borderDash' => [5, 4], 'pointRadius' => 0, 'borderWidth' => 1.5, 'fill' => false] : null,
        ]))],
        'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 12]]],
            'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef0f3']]]],
    ];
@endphp
<x-layouts.app title="Compras en planta" breadcrumb="Compras y precios">
    <x-slot:actions>
        <form method="GET" class="flex items-end gap-2">
            <input type="hidden" name="empresa" value="{{ $vista }}">
            <div>
                <label class="form-label">Mes</label>
                <input type="month" name="mes" value="{{ $c['mes']->format('Y-m') }}" class="form-input w-56" onchange="this.form.submit()">
            </div>
        </form>
        <x-export :url="route('compras.index', ['empresa' => $vista, 'mes' => $c['mes']->format('Y-m')])"/>
        <button class="btn btn-secondary" data-modal-url="{{ route('compras.cuotas', ['mes' => $c['mes']->format('Y-m')]) }}" data-modal-size="md"><x-heroicon-o-flag/> Cuotas del mes</button>
        <button class="btn btn-primary" data-modal-url="{{ route('compras.create', ['empresa' => $vista, 'fecha' => $c['mes']->isSameMonth($hoy) ? $hoy->toDateString() : $c['mes']->copy()->endOfMonth()->toDateString()]) }}" data-modal-size="md"><x-heroicon-o-plus/> Registrar compra</button>
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <nav class="segmented">
            @foreach ($empresas as $e)
                <a href="{{ route('compras.index', ['empresa' => $e->id, 'mes' => $c['mes']->format('Y-m')]) }}" @class(['active' => $vista === (string) $e->id])>{{ $e->nombre }}</a>
            @endforeach
            <a href="{{ route('compras.index', ['empresa' => 'global', 'mes' => $c['mes']->format('Y-m')]) }}" @class(['active' => $vista === 'global'])>Global</a>
        </nav>
        <p class="text-[13px] text-slate-500">{{ ucfirst($c['mes']->translatedFormat('F Y')) }} · {{ $c['dias_con_compra'] }} días con compra</p>
    </div>

    <dl class="ledger !grid-cols-2 lg:!grid-cols-4">
        @foreach (['S10', 'S45', 'M10'] as $k)
            <x-cifra :label="$nombres[$k].' comprado'" :value="num($c['total'][$k])" :hint="$c['cuota'][$k]['cuota'] ? 'Cuota '.num($c['cuota'][$k]['cuota']).' · '.number_format($c['cuota'][$k]['porcentaje'], 1).' %' : 'Sin cuota registrada'"/>
        @endforeach
        <x-cifra label="Total base 10 kg" :value="num($c['base10']['actual'])" :hint="$mesAnt.': '.num($c['base10']['anterior'])" total/>
    </dl>

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,4fr)_minmax(0,8fr)]">
        <section class="card self-start">
            <div class="card-header"><p class="card-title">Compras diarias · {{ $titulo }}</p></div>
            <div class="table-wrap max-h-[46rem]">
                <table class="table table-grid table-compact">
                    <thead class="sticky top-0 z-[1]">
                    <tr class="th-group"><th rowspan="2" class="!text-left">Fecha</th><th colspan="2">Solgas</th><th>Masgas</th></tr>
                    <tr><th class="text-right">S10</th><th class="text-right">S45</th><th class="text-right">S10</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($c['dias'] as $d)
                        @php($sin = ! array_sum($d['cantidades']))
                        <tr @class(['text-slate-400' => $sin, 'bg-panel' => $d['fecha']->isSunday()])>
                            <td class="whitespace-nowrap {{ $sin ? '' : 'font-medium text-slate-900' }}">{{ $d['fecha']->format('d/m/Y') }}</td>
                            @foreach (['S10', 'S45', 'M10'] as $k)
                                <td class="text-right">{{ ($d['cantidades'][$k] ?? 0) ? num($d['cantidades'][$k]) : '–' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td>Total</td>@foreach (['S10', 'S45', 'M10'] as $k)<td class="text-right">{{ num($c['total'][$k]) }}</td>@endforeach</tr></tfoot>
                </table>
            </div>
        </section>

        <div class="space-y-4">
            <section class="card">
                <div class="card-header"><p class="card-title">Cuota del mes</p>
                    <button class="btn btn-ghost btn-sm" data-modal-url="{{ route('compras.cuotas', ['mes' => $c['mes']->format('Y-m')]) }}" data-modal-size="md"><x-heroicon-o-pencil-square/> Editar cuotas</button>
                </div>
                <div class="grid gap-3 p-4 md:grid-cols-3">
                    @foreach (['S10', 'S45', 'M10'] as $k)
                        @php($q = $c['cuota'][$k])
                        @php($proyeccion = $transcurridos ? (int) round($q['avance'] / $transcurridos * $diasMes) : null)
                        <div class="rounded-lg border border-line bg-white p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-[13px] font-semibold text-slate-900">{{ $nombres[$k] }}</p>
                                @if ($q['porcentaje'] !== null)
                                    <span @class(['pill', 'pill-green' => $q['porcentaje'] >= 100, 'pill-amber' => $q['porcentaje'] < 100])>{{ number_format($q['porcentaje'], 1) }} %</span>
                                @endif
                            </div>
                            <p class="mt-2 text-[24px] font-semibold tracking-tight text-slate-900 tabular-nums">{{ num($q['avance']) }} <span class="text-[13px] font-normal text-slate-500">/ {{ $q['cuota'] ? num($q['cuota']) : 'sin cuota' }}</span></p>
                            <div class="progress mt-2"><span style="width: {{ min(100, $q['porcentaje'] ?? 0) }}%" @class(['!bg-emerald-600' => ($q['porcentaje'] ?? 0) >= 100])></span></div>
                            <dl class="mt-3 grid grid-cols-2 gap-2 text-[12px]">
                                <div><dt class="text-slate-500">Diferencia</dt><dd class="font-semibold tabular-nums {{ $q['diferencia'] > 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $q['cuota'] ? num($q['diferencia']) : '—' }}</dd></div>
                                <div><dt class="text-slate-500">Proyección al cierre</dt><dd class="font-semibold tabular-nums text-slate-800">{{ $proyeccion !== null && $q['cuota'] ? num($proyeccion) : '—' }}</dd></div>
                            </dl>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="card">
                    <div class="card-header"><p class="card-title">Comparativa con el mes anterior</p></div>
                    <table class="table table-grid">
                        <thead><tr><th>Producto</th><th class="text-right">{{ $mesAnt }}</th><th class="text-right">{{ $mesAct }}</th><th class="text-right">Sube / baja</th><th class="text-right">%</th></tr></thead>
                        <tbody>
                        @foreach ($c['comparativa'] as $k => $q)
                            <tr @class(['bg-brand-50 font-semibold' => $k === 'TOTAL'])>
                                <td class="whitespace-nowrap {{ $k === 'TOTAL' ? 'text-brand-900' : 'font-medium text-slate-900' }}">{{ $k === 'TOTAL' ? 'Total (base 10 kg)' : $nombres[$k] }}</td>
                                <td class="text-right">{{ num($q['anterior']) }}</td>
                                <td class="text-right">{{ num($q['actual']) }}</td>
                                <td class="text-right">
                                    <span class="inline-flex items-center gap-1 {{ $q['variacion'] > 0 ? 'text-emerald-700' : ($q['variacion'] < 0 ? 'text-red-700' : 'text-slate-500') }}">
                                        @if ($q['variacion'] > 0)<x-heroicon-m-arrow-trending-up class="h-4 w-4"/>@elseif ($q['variacion'] < 0)<x-heroicon-m-arrow-trending-down class="h-4 w-4"/>@else<x-heroicon-m-minus class="h-4 w-4"/>@endif
                                        {{ $q['variacion'] > 0 ? '+' : '' }}{{ num($q['variacion']) }}
                                    </span>
                                </td>
                                <td class="text-right">{{ $q['porcentaje'] !== null ? number_format($q['porcentaje'], 2).' %' : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <p class="border-t border-line px-4 py-2 text-[12px] text-slate-500">Base 10 kg: S10 + Masgas + S45 × 4.5.</p>
                </section>
                <section class="card">
                    <div class="card-header"><p class="card-title">Comparativa a base 10 kg · {{ $titulo }}</p></div>
                    <div class="h-64 p-4"><canvas data-chart="{{ json_encode($grafico) }}"></canvas></div>
                </section>
            </div>

            <section class="card">
                <div class="card-header"><p class="card-title">Avance acumulado S10 frente a la cuota</p></div>
                <div class="h-60 p-4"><canvas data-chart="{{ json_encode($avance) }}"></canvas></div>
            </section>
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
                        <td><span @class(['pill', 'pill-blue' => $r->origen === 'parte', 'pill-slate' => $r->origen !== 'parte'])>{{ \App\Models\CompraPlanta::ORIGENES[$r->origen] ?? $r->origen }}</span></td>
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
