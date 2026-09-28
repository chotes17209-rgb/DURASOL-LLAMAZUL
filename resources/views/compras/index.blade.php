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
            'backgroundColor' => ['#a3adbd', '#173566'], 'borderRadius' => 0, 'maxBarThickness' => 72]]],
        'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef0f3']]]],
    ];
    $avance = [
        'type' => 'line',
        'data' => ['labels' => $c['dias']->map(fn ($d) => $d['fecha']->format('d'))->values(), 'datasets' => array_values(array_filter([
            ['label' => 'Compras acumuladas S10', 'data' => $serie->values(), 'borderColor' => '#173566', 'backgroundColor' => 'rgba(23,53,102,.06)', 'fill' => true, 'tension' => 0.25, 'pointRadius' => 0, 'borderWidth' => 2],
            $c['cuota']['S10']['cuota'] ? ['label' => 'Cuota S10', 'data' => $c['dias']->map(fn ($d, $i) => round($c['cuota']['S10']['cuota'] * ($i + 1) / $diasMes))->values(),
                'borderColor' => '#8a94a6', 'borderDash' => [5, 4], 'pointRadius' => 0, 'borderWidth' => 1.5, 'fill' => false] : null,
        ]))],
        'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 12]]],
            'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef0f3']]]],
    ];
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
                <div class="table-wrap">
                    <table class="table table-grid">
                        <thead><tr><th>Producto</th><th class="text-right">Cuota</th><th class="text-right">Avance</th><th class="w-56">% de avance</th><th class="text-right">Diferencia</th><th class="text-right">Proyección al cierre</th></tr></thead>
                        <tbody>
                        @foreach (['S10', 'S45', 'M10'] as $k)
                            @php($q = $c['cuota'][$k])
                            @php($proyeccion = $transcurridos ? (int) round($q['avance'] / $transcurridos * $diasMes) : null)
                            <tr>
                                <td class="font-medium whitespace-nowrap text-slate-900">{{ $nombres[$k] }}</td>
                                <td class="text-right">{{ $q['cuota'] ? num($q['cuota']) : '—' }}</td>
                                <td class="text-right font-semibold">{{ num($q['avance']) }}</td>
                                <td>
                                    @if ($q['porcentaje'] !== null)
                                        <div class="flex items-center gap-2">
                                            <div class="progress flex-1"><span style="width: {{ min(100, $q['porcentaje']) }}%"></span></div>
                                            <span class="w-16 text-right text-[12px] font-semibold tabular-nums text-slate-800">{{ number_format($q['porcentaje'], 2) }} %</span>
                                        </div>
                                    @else
                                        <span class="text-[12px] text-slate-400">Sin cuota</span>
                                    @endif
                                </td>
                                <td class="text-right font-semibold {{ $q['cuota'] && $q['diferencia'] > 0 ? 'text-red-700' : 'text-slate-800' }}">{{ $q['cuota'] ? num($q['diferencia']) : '—' }}</td>
                                <td class="text-right">{{ $proyeccion !== null && $q['cuota'] ? num($proyeccion) : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-slate-200 px-4 py-2 text-[12px] text-slate-500">Diferencia: lo que falta comprar para cumplir la cuota (negativo: cuota superada). Proyección: ritmo diario actual llevado al fin de mes.</p>
            </section>

            <section class="card">
                <div class="card-header"><p class="card-title">Comparativa con el mes anterior</p><span class="text-[12px] text-slate-500">Base 10 kg: S10 + Masgas + S45 × 4.5</span></div>
                <div class="table-wrap">
                    <table class="table table-grid">
                        <thead><tr><th>Producto</th><th class="text-right">{{ $mesAnt }}</th><th class="text-right">{{ $mesAct }}</th><th class="text-right">Variación</th><th class="text-right">% respecto a {{ mb_strtolower($mesAnt) }}</th></tr></thead>
                        <tbody>
                        @foreach ($c['comparativa'] as $k => $q)
                            <tr @class(['font-semibold bg-slate-50' => $k === 'TOTAL'])>
                                <td class="whitespace-nowrap text-slate-900 {{ $k === 'TOTAL' ? '' : 'font-medium' }}">{{ $k === 'TOTAL' ? 'Total (base 10 kg)' : $nombres[$k] }}</td>
                                <td class="text-right">{{ num($q['anterior']) }}</td>
                                <td class="text-right">{{ num($q['actual']) }}</td>
                                <td class="text-right {{ $q['variacion'] > 0 ? 'text-emerald-700' : ($q['variacion'] < 0 ? 'text-red-700' : 'text-slate-500') }}">{{ $q['variacion'] > 0 ? '+' : ($q['variacion'] < 0 ? '−' : '') }}{{ num(abs($q['variacion'])) }}</td>
                                <td class="text-right">{{ $q['porcentaje'] !== null ? number_format($q['porcentaje'], 2).' %' : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-4 2xl:grid-cols-2">
                <section class="card">
                    <div class="card-header"><p class="card-title">Base 10 kg · {{ $mesAnt }} frente a {{ mb_strtolower($mesAct) }}</p></div>
                    <div class="h-60 p-4"><canvas data-chart="{{ json_encode($grafico) }}"></canvas></div>
                </section>
                <section class="card">
                    <div class="card-header"><p class="card-title">Avance acumulado S10 frente a la cuota</p></div>
                    <div class="h-60 p-4"><canvas data-chart="{{ json_encode($avance) }}"></canvas></div>
                </section>
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
