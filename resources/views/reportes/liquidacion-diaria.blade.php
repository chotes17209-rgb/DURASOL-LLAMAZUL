@php
    $importes = \App\Support\HojaLiquidacionDiaria::IMPORTES;
    $gruposProducto = \App\Support\HojaLiquidacionDiaria::grupos($productos);
    $codigos = array_merge(...array_values($gruposProducto));
    $n = fn ($v, $d = 2) => (float) $v == 0 ? '-' : num($v, $d);
@endphp
<x-layouts.app title="Hoja de liquidación diaria" breadcrumb="Reportes">
    <x-slot:actions>
        <x-export :url="route('reportes.liquidacion-diaria', ['fecha' => $fecha->toDateString()])"/>
    </x-slot:actions>
    <x-slot:filters>
        <form method="GET">
            <div class="fb">
                <label>Fecha</label>
                <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-44" onchange="this.form.submit()">
            </div>
            <button class="btn btn-primary">Aplicar</button>
        </form>
    </x-slot:filters>

    <div class="hoja">
        <div class="grid items-center gap-3 md:grid-cols-[1fr_2fr_1fr]">
            <div class="flex items-center gap-2">
                <img src="{{ asset('img/durasol.jpg') }}" alt="Durasol" class="h-8">
                <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-5">
            </div>
            <p class="hoja-titulo">HOJA DE LIQUIDACIÓN DIARIA</p>
            <div class="md:text-right"><span class="hoja-fecha"><span>FECHA</span><span>{{ $fecha->format('d/m/Y') }}</span></span></div>
        </div>

        <div class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_19rem]">
            <div class="min-w-0">
                @foreach ($grupos as $i => $g)
                    @php($esRuta = $i === 1)
                    <p class="hoja-seccion">{{ $g['titulo'] }}</p>
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                            <tr>
                                <th rowspan="2">{{ $esRuta ? 'FECHA SALIDA' : 'PLACA' }}</th><th rowspan="2">RESPONSABLE</th>
                                @foreach ($gruposProducto as $nombre => $lista)<th colspan="{{ count($lista) }}">{{ $nombre }}</th>@endforeach
                                @foreach ($importes as $k => $t)<th rowspan="2">{{ mb_strtoupper($esRuta && $k === 'por_depositar' ? 'Saldo' : $t) }}</th>@endforeach
                                <th rowspan="2">ESTADO</th>
                            </tr>
                            <tr>@foreach ($codigos as $c)<th>{{ $c }}</th>@endforeach</tr>
                            </thead>
                            <tbody>
                            @forelse ($g['filas'] as $f)
                                <tr class="cursor-pointer" data-modal-url="{{ route('liquidaciones.show', $f['liquidacion']) }}" data-modal-size="xl" title="Ver liquidación {{ $f['liquidacion']->codigo }}">
                                    <td class="font-mono text-[11px]">{{ $esRuta ? fecha($f['fecha_venta']) : $f['placa'] }}</td>
                                    <td class="font-semibold">{{ $f['responsable'] }}</td>
                                    @foreach ($codigos as $c)<td class="n">{{ $n($f['cantidades'][$c] ?? 0, 0) }}</td>@endforeach
                                    @foreach ($importes as $k => $t)<td class="n {{ in_array($k, ['venta', 'por_depositar']) ? 'rojo' : '' }}">{{ $n($f[$k]) }}</td>@endforeach
                                    <td><x-status :value="$f['liquidacion']->estado"/></td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 3 + count($codigos) + count($importes) }}" class="py-4 text-center text-slate-400">Sin liquidaciones para esta fecha.</td></tr>
                            @endforelse
                            <tr class="total">
                                <td colspan="2">TOTALES</td>
                                @foreach ($codigos as $c)<td class="n">{{ $n($g['total']['cantidades'][$c] ?? 0, 0) }}</td>@endforeach
                                @foreach ($importes as $k => $t)<td class="n {{ in_array($k, ['venta', 'por_depositar']) ? 'rojo' : '' }}">{{ $n($g['total'][$k]) }}</td>@endforeach
                                <td></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach

                <div class="grid gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
                    <div>
                        <p class="hoja-seccion">Por depositar</p>
                        <table>
                            <tbody>
                            @forelse ($porDepositar as $responsable => $monto)
                                <tr><td>{{ $responsable }}</td><td class="n">{{ $n($monto) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-slate-400">—</td></tr>
                            @endforelse
                            <tr class="total"><td>TOTAL POR DEPOSITAR</td><td class="n">{{ $n($resumenDeposito['por_depositar']) }}</td></tr>
                            <tr><td>(−) Asignación de caja chica</td><td class="n">{{ $n($resumenDeposito['caja_chica']) }}</td></tr>
                            <tr><td>(−) Planilla</td><td class="n">{{ $n($resumenDeposito['planilla']) }}</td></tr>
                            <tr class="total"><td class="text-[13px]">TOTAL A DEPOSITAR</td><td class="n rojo text-[13px]">{{ $n($resumenDeposito['total']) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="min-w-0">
                        <p class="hoja-seccion">Depósitos realizados</p>
                        <div class="overflow-x-auto">
                            <table>
                                <thead><tr><th>N°</th><th>RESPONSABLE</th><th>FECHA</th><th>BANCO</th><th>EMPRESA</th><th>QUIÉN / OPERACIÓN</th><th>IMPORTE</th></tr></thead>
                                <tbody>
                                @forelse ($depositos as $i => $x)
                                    <tr>
                                        <td class="n">{{ $i + 1 }}</td><td class="font-semibold">{{ $x['responsable'] ?? '—' }}</td><td>{{ $x['fecha'] ? fecha($x['fecha']) : '' }}</td>
                                        <td>{{ $x['banco'] }}</td><td>{{ $x['empresa'] }}</td><td class="text-slate-600">{{ $x['quien'] ?: $x['operacion'] }}</td><td class="n">{{ $n($x['monto']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="py-4 text-center text-slate-400">Sin depósitos este día.</td></tr>
                                @endforelse
                                <tr class="total"><td colspan="6">TOTAL DEPÓSITOS</td><td class="n">{{ $n($depositos->sum('monto')) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-[12px] text-slate-500">Otros egresos de caja del día: <b class="text-slate-800">{{ soles($gastosCaja) }}</b></p>
                    </div>
                </div>
            </div>

            <div class="min-w-0">
                <p class="hoja-seccion">Detalle ventas</p>
                <table>
                    <thead><tr><th>PRODUCTO</th><th>CANT.</th><th>P.U.</th><th>TOTAL</th></tr></thead>
                    <tbody>
                    @forelse ($porProductoPrecio as $codigo => $p)
                        @foreach ($p['filas'] as $x)
                            <tr><td class="font-semibold">{{ $loop->first ? $codigo : '' }}</td><td class="n">{{ $n($x->cantidad, 0) }}</td><td class="n">{{ $n($x->precio) }}</td><td class="n">{{ $n($x->total) }}</td></tr>
                        @endforeach
                        <tr class="sub"><td>TOTAL {{ $codigo }}</td><td class="n">{{ $n($p['cantidad'], 0) }}</td><td></td><td class="n">{{ $n($p['total']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-slate-400">Sin ventas.</td></tr>
                    @endforelse
                    <tr class="total"><td>TOTAL</td><td class="n">{{ $n($detallePrecios->sum('cantidad'), 0) }}</td><td></td><td class="n rojo">{{ $n($detallePrecios->sum('total')) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 border-t border-[#e5e8ec] pt-2 text-[11.5px] text-slate-500">
            Por depositar = venta + cobranza − crédito − varios − FISE − vouchers − depósitos. Reparto local por fecha de venta; ruta por fecha de liquidación (las pendientes muestran su saldo).
            El PDF y el Excel se descargan con esta misma hoja.
        </p>
    </div>
</x-layouts.app>
