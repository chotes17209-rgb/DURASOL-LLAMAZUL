@php
    $res = $resultado;
    $pct = fn ($v) => number_format($v, 2).' %';
    $sobreVentas = fn ($v) => $res['ventas'] > 0 ? $pct($v / $res['ventas'] * 100) : '—';
    $grafico = [
        'type' => 'bar',
        'data' => [
            'labels' => $por_dia->map(fn ($d) => $d['fecha']->format('d/m'))->values(),
            'datasets' => [
                ['type' => 'bar', 'label' => 'Utilidad bruta (S/)', 'data' => $por_dia->pluck('utilidad')->values(), 'backgroundColor' => '#173566', 'yAxisID' => 'y', 'maxBarThickness' => 22, 'order' => 2],
                ['type' => 'line', 'label' => 'Margen bruto (%)', 'data' => $por_dia->pluck('margen')->values(), 'borderColor' => '#d9661a', 'backgroundColor' => '#d9661a', 'yAxisID' => 'y1', 'tension' => 0.25, 'pointRadius' => 2, 'borderWidth' => 2, 'order' => 1],
            ],
        ],
        'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 12]]],
            'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef0f3']],
                'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['display' => false], 'ticks' => ['callback' => null]]]],
    ];
    $exportar = array_filter(['mes' => request('mes'), 'desde' => request('desde'), 'hasta' => request('hasta'), 'empresa_id' => request('empresa_id')]);
@endphp
<x-layouts.app title="Rentabilidad" breadcrumb="Gerencia">
    <x-slot:actions>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="form-label">Mes</label>
                <input type="month" name="mes" value="{{ $mes }}" class="form-input w-56" onchange="this.form.submit()">
            </div>
            <div>
                <label class="form-label">Empresa</label>
                <select name="empresa_id" class="form-input w-40" onchange="this.form.submit()">
                    <option value="">Consolidado</option>
                    @foreach ($empresas as $id => $nombre)<option value="{{ $id }}" @selected((int) request('empresa_id') === $id)>{{ $nombre }}</option>@endforeach
                </select>
            </div>
        </form>
        <x-export :url="route('reportes.rentabilidad', $exportar)"/>
    </x-slot:actions>

    <section class="doc-head">
        <div class="doc-band">
            <div>
                <p class="text-[12px] text-slate-500">Estado de resultados de gestión{{ $empresa ? ' · '.$empresa : ' · Consolidado Durasol y Llamazul' }}</p>
                <p class="text-[17px] font-semibold text-brand-900">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>
            </div>
            <p class="text-right text-[12px] leading-snug text-slate-600">
                {{ num($res['balones']) }} balones vendidos<br>
                @if ($res['traslados'])<span>{{ num($res['traslados']) }} salidas sin valor (traslados)</span>@endif
            </p>
        </div>
        <dl class="grid grid-cols-2 lg:grid-cols-5">
            <x-cifra label="Ventas netas" :value="soles($res['ventas'])" :hint="num($res['balones']).' unidades'"/>
            <x-cifra label="Costo de ventas" :value="soles($res['costo'])" :hint="$sobreVentas($res['costo']).' de las ventas'"/>
            <x-cifra label="Utilidad bruta" :value="soles($res['utilidad_bruta'])" :hint="'Margen bruto '.$pct($res['margen_bruto'])" :tone="$res['utilidad_bruta'] < 0 ? 'red' : null"/>
            <x-cifra label="Gastos operativos" :value="soles($res['total_gastos'])" :hint="$empresa ? 'solo en el consolidado' : $sobreVentas($res['total_gastos']).' de las ventas'"/>
            <x-cifra label="Utilidad operativa" :value="soles($res['utilidad_operativa'])" :hint="'Margen operativo '.$pct($res['margen_operativo'])" total/>
        </dl>
    </section>

    @if ($res['sin_costo'] > 0)
        <p class="help mt-3 border-amber-300 bg-amber-50 text-amber-900">Hay ventas por {{ soles($res['sin_costo']) }} de productos sin costo registrado; su costo se toma como cero. Registre el costo referencial en <a class="font-semibold underline" href="{{ route('productos.index') }}">Productos</a>.</p>
    @endif

    <div x-data="{ tab: 'resultado' }" class="mt-4">
        <nav class="segmented mb-4 max-w-full overflow-x-auto">
            @foreach (['resultado' => 'Estado de resultados', 'productos' => 'Por presentación y empresa', 'diario' => 'Rentabilidad diaria', 'responsables' => 'Por responsable', 'compras' => 'Compras frente a ventas'] as $k => $t)
                <button type="button" class="whitespace-nowrap" :class="tab === '{{ $k }}' && 'active'" @click="tab = '{{ $k }}'">{{ $t }}</button>
            @endforeach
        </nav>

        {{-- Estado de resultados --}}
        <div x-show="tab === 'resultado'" class="grid gap-4 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <section class="card self-start">
                <div class="card-header"><p class="card-title">Estado de resultados</p><span class="text-[12px] text-slate-500">Importe (S/) · % ventas</span></div>
                <table class="recibo">
                    <tr class="subtotal"><td>Ventas netas</td><td>{{ num($res['ventas'], 2) }}</td><td class="w-20 text-right text-slate-500">100.00 %</td></tr>
                    <tr class="resta"><td>(−) Costo de ventas</td><td>({{ num($res['costo'], 2) }})</td><td class="text-right text-slate-500">{{ $sobreVentas($res['costo']) }}</td></tr>
                    <tr class="subtotal"><td>Utilidad bruta</td><td>{{ num($res['utilidad_bruta'], 2) }}</td><td class="text-right">{{ $pct($res['margen_bruto']) }}</td></tr>
                    @forelse (collect($res['gastos'])->groupBy('grupo') as $grupo => $items)
                        <tr><td colspan="3" class="bg-panel !py-1 !text-left text-[12px] font-semibold text-slate-600">Gastos operativos · {{ $grupo }}</td></tr>
                        @foreach ($items as $g)
                            <tr class="resta"><td class="!pl-7">(−) {{ $g['concepto'] }}</td><td>({{ num($g['monto'], 2) }})</td><td class="text-right text-slate-500">{{ $sobreVentas($g['monto']) }}</td></tr>
                        @endforeach
                    @empty
                        <tr class="resta"><td>(−) Gastos operativos</td><td>{{ $empresa ? '—' : '0.00' }}</td><td></td></tr>
                    @endforelse
                    @if ($res['total_gastos'])
                        <tr class="subtotal"><td>Total gastos operativos</td><td>({{ num($res['total_gastos'], 2) }})</td><td class="text-right">{{ $sobreVentas($res['total_gastos']) }}</td></tr>
                    @endif
                    @if ($res['otros_ingresos'])
                        <tr><td>(+) Otros ingresos</td><td>{{ num($res['otros_ingresos'], 2) }}</td><td></td></tr>
                    @endif
                    <tr class="final"><td>Utilidad operativa</td><td>S/ {{ num($res['utilidad_operativa'], 2) }}</td><td class="text-right">{{ $pct($res['margen_operativo']) }}</td></tr>
                </table>
                <div class="border-t border-line px-4 py-3 text-[12px] leading-relaxed text-slate-500">
                    <p><b class="text-slate-700">Criterios.</b> Ventas y costo por fecha de venta (liquidaciones no anuladas). Costo unitario: precio de compra en planta vigente a la fecha de la venta, promedio de las instalaciones de la empresa; Contigas y envases, costo referencial del producto.</p>
                    <p class="mt-1">No afectan el resultado: salidas a precio 0 (traslados), vales FISE, créditos, cobranzas, depósitos ni reposiciones de caja chica.</p>
                </div>
            </section>

            <div class="space-y-4">
                <section class="card">
                    <div class="card-header"><p class="card-title">Utilidad bruta y margen por día</p></div>
                    <div class="h-72 p-4"><canvas data-chart="{{ json_encode($grafico) }}"></canvas></div>
                </section>
                <section class="card">
                    <div class="card-header"><p class="card-title">Indicadores</p></div>
                    <dl class="dl-grid !rounded-none !border-0 sm:!grid-cols-4">
                        <div><dt>Precio promedio por balón</dt><dd>{{ $res['balones'] ? soles($res['ventas'] / $res['balones']) : '—' }}</dd></div>
                        <div><dt>Costo promedio por balón</dt><dd>{{ $res['balones'] ? soles($res['costo'] / $res['balones']) : '—' }}</dd></div>
                        <div><dt>Utilidad bruta por balón</dt><dd>{{ $res['balones'] ? soles($res['utilidad_bruta'] / $res['balones']) : '—' }}</dd></div>
                        <div><dt>Utilidad operativa por balón</dt><dd>{{ $res['balones'] ? soles($res['utilidad_operativa'] / $res['balones']) : '—' }}</dd></div>
                        <div><dt>Días con venta</dt><dd>{{ $por_dia->where('ventas', '>', 0)->count() }}</dd></div>
                        <div><dt>Venta promedio diaria</dt><dd>{{ $por_dia->where('ventas', '>', 0)->count() ? soles($res['ventas'] / $por_dia->where('ventas', '>', 0)->count()) : '—' }}</dd></div>
                        <div><dt>Utilidad bruta promedio diaria</dt><dd>{{ $por_dia->where('ventas', '>', 0)->count() ? soles($res['utilidad_bruta'] / $por_dia->where('ventas', '>', 0)->count()) : '—' }}</dd></div>
                        <div><dt>Gasto operativo / utilidad bruta</dt><dd>{{ $res['utilidad_bruta'] > 0 ? $pct($res['total_gastos'] / $res['utilidad_bruta'] * 100) : '—' }}</dd></div>
                    </dl>
                </section>
            </div>
        </div>

        {{-- Por presentación y empresa --}}
        <div x-show="tab === 'productos'" x-cloak class="space-y-4">
            <section class="card">
                <div class="card-header"><p class="card-title">Rentabilidad por presentación</p></div>
                <div class="table-wrap">
                    <table class="table table-grid">
                        <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Precio prom.</th><th class="text-right">Costo prom.</th><th class="text-right">Utilidad x unidad</th><th class="text-right">Ventas</th><th class="text-right">Costo de ventas</th><th class="text-right">Utilidad bruta</th><th class="text-right">Margen</th><th class="text-right">Participación</th></tr></thead>
                        <tbody>
                        @forelse ($por_producto as $f)
                            <tr>
                                <td><span class="font-semibold text-slate-900">{{ $f['producto']->codigo }}</span> <span class="text-[12px] text-slate-500">{{ $f['producto']->nombre }}</span>@if($f['sin_costo']) <x-badge color="amber">sin costo</x-badge>@endif</td>
                                <td class="text-right">{{ num($f['cantidad']) }}</td>
                                <td class="text-right">{{ num($f['precio_promedio'], 2) }}</td>
                                <td class="text-right">{{ num($f['costo_promedio'], 2) }}</td>
                                <td class="text-right">{{ num($f['utilidad_unitaria'], 2) }}</td>
                                <td class="text-right">{{ num($f['ventas'], 2) }}</td>
                                <td class="text-right">{{ num($f['costo'], 2) }}</td>
                                <td class="text-right font-semibold {{ $f['utilidad'] < 0 ? 'text-red-700' : '' }}">{{ num($f['utilidad'], 2) }}</td>
                                <td class="text-right">{{ $pct($f['margen']) }}</td>
                                <td class="text-right text-slate-500">{{ $res['utilidad_bruta'] > 0 ? $pct($f['utilidad'] / $res['utilidad_bruta'] * 100) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10"><x-empty text="Sin ventas en el periodo."/></td></tr>
                        @endforelse
                        </tbody>
                        <tfoot><tr><td>Total</td><td class="text-right">{{ num($res['balones']) }}</td><td colspan="3"></td><td class="text-right">{{ num($res['ventas'], 2) }}</td><td class="text-right">{{ num($res['costo'], 2) }}</td><td class="text-right">{{ num($res['utilidad_bruta'], 2) }}</td><td class="text-right">{{ $pct($res['margen_bruto']) }}</td><td class="text-right">100.00 %</td></tr></tfoot>
                    </table>
                </div>
            </section>
            <section class="card">
                <div class="card-header"><p class="card-title">Rentabilidad por empresa</p></div>
                <table class="table table-grid">
                    <thead><tr><th>Empresa</th><th class="text-right">Cantidad</th><th class="text-right">Traslados sin valor</th><th class="text-right">Ventas</th><th class="text-right">Costo de ventas</th><th class="text-right">Utilidad bruta</th><th class="text-right">Margen</th></tr></thead>
                    <tbody>
                    @foreach ($por_empresa as $nombre => $f)
                        <tr><td class="font-semibold text-slate-900">{{ $nombre }}</td><td class="text-right">{{ num($f['cantidad']) }}</td><td class="text-right">{{ $f['traslados'] ? num($f['traslados']) : '' }}</td><td class="text-right">{{ num($f['ventas'], 2) }}</td><td class="text-right">{{ num($f['costo'], 2) }}</td><td class="text-right font-semibold">{{ num($f['utilidad'], 2) }}</td><td class="text-right">{{ $pct($f['margen']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        {{-- Rentabilidad diaria (hoja RENTABILIDAD) --}}
        <section x-show="tab === 'diario'" x-cloak class="card">
            <div class="card-header"><p class="card-title">Rentabilidad diaria</p><span class="text-[12px] text-slate-500">Cantidades vendidas con valor</span></div>
            <div class="table-wrap">
                <table class="table table-grid table-compact">
                    <thead><tr><th>Fecha</th>@foreach ($productos as $p)<th class="text-right">{{ $p->codigo }}</th>@endforeach<th class="text-right">Importe total</th><th class="text-right">Costo</th><th class="text-right">Utilidad bruta</th><th class="text-right">Margen</th></tr></thead>
                    <tbody>
                    @forelse ($por_dia as $d)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-slate-900">{{ ucfirst($d['fecha']->translatedFormat('D d/m')) }}</td>
                            @foreach ($productos as $p)<td class="text-right">{{ ($d['cantidades'][$p->id] ?? 0) ? num($d['cantidades'][$p->id]) : '' }}</td>@endforeach
                            <td class="text-right">{{ num($d['ventas'], 2) }}</td>
                            <td class="text-right text-slate-600">{{ num($d['costo'], 2) }}</td>
                            <td class="text-right font-semibold {{ $d['utilidad'] < 0 ? 'text-red-700' : '' }}">{{ num($d['utilidad'], 2) }}</td>
                            <td class="text-right">{{ $pct($d['margen']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $productos->count() + 5 }}"><x-empty text="Sin ventas en el periodo."/></td></tr>
                    @endforelse
                    </tbody>
                    <tfoot><tr><td>Total</td>@foreach ($productos as $p)<td class="text-right">{{ num($por_dia->sum(fn ($d) => $d['cantidades'][$p->id] ?? 0)) }}</td>@endforeach<td class="text-right">{{ num($res['ventas'], 2) }}</td><td class="text-right">{{ num($res['costo'], 2) }}</td><td class="text-right">{{ num($res['utilidad_bruta'], 2) }}</td><td class="text-right">{{ $pct($res['margen_bruto']) }}</td></tr></tfoot>
                </table>
            </div>
        </section>

        {{-- Por responsable --}}
        <section x-show="tab === 'responsables'" x-cloak class="card">
            <div class="card-header"><p class="card-title">Rentabilidad por responsable</p><span class="text-[12px] text-slate-500">Ordenado por utilidad bruta</span></div>
            <div class="table-wrap">
                <table class="table table-grid table-compact">
                    <thead><tr><th>Responsable</th>@foreach ($productos as $p)<th class="text-right">{{ $p->codigo }}</th>@endforeach<th class="text-right">Ventas</th><th class="text-right">Utilidad bruta</th><th class="text-right">Margen</th><th class="text-right">Utilidad x unidad</th><th class="text-right">Participación</th></tr></thead>
                    <tbody>
                    @foreach ($por_responsable as $nombre => $f)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $nombre }}</td>
                            @foreach ($productos as $p)<td class="text-right">{{ ($f['cantidades'][$p->id] ?? 0) ? num($f['cantidades'][$p->id]) : '' }}</td>@endforeach
                            <td class="text-right">{{ num($f['ventas'], 2) }}</td>
                            <td class="text-right font-semibold">{{ num($f['utilidad'], 2) }}</td>
                            <td class="text-right">{{ $pct($f['margen']) }}</td>
                            <td class="text-right">{{ num($f['utilidad_unitaria'], 2) }}</td>
                            <td class="text-right text-slate-500">{{ $res['utilidad_bruta'] > 0 ? $pct($f['utilidad'] / $res['utilidad_bruta'] * 100) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Compras en planta frente a ventas (hoja STOCK) --}}
        <div x-show="tab === 'compras'" x-cloak class="grid gap-4 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <section class="card self-start">
                <div class="card-header"><p class="card-title">Comprado en planta frente a vendido</p></div>
                <table class="table table-grid">
                    <thead><tr><th>Empresa</th><th>Producto</th><th class="text-right">Comprado</th><th class="text-right">Vendido</th><th class="text-right">Diferencia</th></tr></thead>
                    <tbody>
                    @forelse ($compras['resumen'] as $c)
                        <tr @class(['bg-brand-50' => $c['total'] ?? false])><td class="font-semibold text-slate-900">{{ $c['empresa'] }}</td><td>{{ $c['codigo'] }}</td><td class="text-right">{{ num($c['compras']) }}</td><td class="text-right">{{ num($c['ventas']) }}</td>
                            <td class="text-right font-semibold {{ $c['diferencia'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $c['diferencia'] > 0 ? '+' : '' }}{{ num($c['diferencia']) }}</td></tr>
                    @empty
                        <tr><td colspan="5"><x-empty text="Sin compras ni ventas en el periodo."/></td></tr>
                    @endforelse
                    </tbody>
                </table>
                <p class="border-t border-line px-4 py-2.5 text-[12px] text-slate-500">Comprado: ingresos de llenos desde planta del parte diario (y compras importadas del Excel). Vendido: incluye traslados a precio 0. Una diferencia positiva queda en stock; una negativa se cubrió con stock anterior.</p>
            </section>
            <section class="card">
                <div class="card-header"><p class="card-title">Detalle diario</p></div>
                <div class="table-wrap max-h-[36rem]">
                    <table class="table table-grid table-compact">
                        <thead>
                        <tr class="th-group"><th rowspan="2" class="!bg-head !text-left align-bottom !text-slate-700">Fecha</th><th colspan="{{ count($compras['codigos']) }}">Comprado</th><th colspan="{{ count($compras['codigos']) }}">Vendido</th><th colspan="{{ count($compras['codigos']) }}">Diferencia</th></tr>
                        <tr>@foreach (['c', 'v', 'd'] as $_)@foreach ($compras['codigos'] as $c)<th class="text-right">{{ $c }}</th>@endforeach @endforeach</tr>
                        </thead>
                        <tbody>
                        @foreach ($compras['diario'] as $d)
                            <tr>
                                <td class="whitespace-nowrap font-medium text-slate-900">{{ $d['fecha']->format('d/m') }}</td>
                                @foreach ($compras['codigos'] as $c)<td class="text-right">{{ $d['compras'][$c] ?: '' }}</td>@endforeach
                                @foreach ($compras['codigos'] as $c)<td class="text-right">{{ $d['ventas'][$c] ?: '' }}</td>@endforeach
                                @foreach ($compras['codigos'] as $c)@php($dif = $d['compras'][$c] - $d['ventas'][$c])<td class="text-right {{ $dif < 0 ? 'text-red-700' : '' }}">{{ $dif ?: '' }}</td>@endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
