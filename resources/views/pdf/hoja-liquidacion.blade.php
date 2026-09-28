@php
    $importes = \App\Support\HojaLiquidacionDiaria::IMPORTES;
    $gruposProducto = \App\Support\HojaLiquidacionDiaria::grupos($productos);
    $codigos = array_merge(...array_values($gruposProducto));
    $n = fn ($v, $d = 2) => (float) $v == 0 ? '-' : number_format((float) $v, $d);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja de liquidación diaria {{ $fecha->format('d/m/Y') }}</title>
    <style>
        @page { margin: 26px 26px 38px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 6.6px; color: #111; }
        .cab { width: 100%; margin-bottom: 8px; }
        .cab td { vertical-align: middle; }
        .logos img { height: 28px; margin-right: 8px; }
        .titulo { border: 2px solid #111; text-align: center; font-size: 13px; font-weight: bold; padding: 4px; letter-spacing: .5px; }
        .fecha { border-collapse: collapse; margin-left: auto; }
        .fecha td { border: 1px solid #555; padding: 3px 8px; font-weight: bold; }
        .fecha .k { background: #bdd7ee; }
        .fecha .v { background: #ffff00; font-size: 10px; }
        h3 { font-size: 8.5px; margin: 10px 0 3px; text-transform: uppercase; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { background: #bdd7ee; font-weight: bold; padding: 2px 2px; border: 1px solid #555; text-align: center; }
        table.t td { padding: 1.5px 2px; border: 1px solid #888; }
        table.t tr.total td { background: #bdd7ee; font-weight: bold; }
        table.t tr.sub td { background: #eef3fa; font-weight: bold; }
        .n { text-align: right; white-space: nowrap; }
        .rojo { color: #c00000; font-weight: bold; }
        .vacio { text-align: center; color: #888; padding: 6px; }
                .pie { position: fixed; bottom: -24px; left: 0; right: 0; color: #888; font-size: 7px; }
    </style>
</head>
<body>
<table class="cab">
    <tr>
        <td class="logos" style="width: 25%"><img src="{{ $logos[0] }}" alt="Durasol"><img src="{{ $logos[1] }}" alt="Llamazul" style="height: 17px"></td>
        <td style="width: 50%"><div class="titulo">HOJA DE LIQUIDACIÓN DIARIA</div></td>
        <td style="width: 25%"><table class="fecha"><tr><td class="k">FECHA</td><td class="v">{{ $fecha->format('d/m/Y') }}</td></tr></table></td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse"><tr>
<td style="vertical-align: top; padding-right: 10px">
    @foreach ($grupos as $i => $g)
        @php($esRuta = $i === 1)
        <h3>{{ $g['titulo'] }}</h3>
        <table class="t">
            <thead>
            <tr>
                <th rowspan="2">{{ $esRuta ? 'F. SALIDA' : 'PLACA' }}</th><th rowspan="2">RESPONSABLE</th>
                @foreach ($gruposProducto as $nombre => $lista)<th colspan="{{ count($lista) }}">{{ $nombre }}</th>@endforeach
                @foreach ($importes as $k => $t)<th rowspan="2">{{ mb_strtoupper($esRuta && $k === 'por_depositar' ? 'Saldo' : $t) }}</th>@endforeach
                <th rowspan="2">ESTADO</th>
            </tr>
            <tr>@foreach ($codigos as $c)<th>{{ $c }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @forelse ($g['filas'] as $f)
                <tr>
                    <td>{{ $esRuta ? $f['fecha_venta']->format('d/m/Y') : $f['placa'] }}</td>
                    <td>{{ $f['responsable'] }}</td>
                    @foreach ($codigos as $c)<td class="n">{{ $n($f['cantidades'][$c] ?? 0, 0) }}</td>@endforeach
                    @foreach ($importes as $k => $t)<td class="n {{ in_array($k, ['venta', 'por_depositar']) ? 'rojo' : '' }}">{{ $n($f[$k]) }}</td>@endforeach
                    <td>{{ $f['liquidacion']->estado->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 3 + count($codigos) + count($importes) }}" class="vacio">Sin liquidaciones para esta fecha.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="2">TOTALES</td>
                @foreach ($codigos as $c)<td class="n">{{ $n($g['total']['cantidades'][$c] ?? 0, 0) }}</td>@endforeach
                @foreach ($importes as $k => $t)<td class="n {{ in_array($k, ['venta', 'por_depositar']) ? 'rojo' : '' }}">{{ $n($g['total'][$k]) }}</td>@endforeach
                <td></td>
            </tr>
            </tbody>
        </table>
    @endforeach

    <table style="width: 100%; border-collapse: collapse"><tr>
        <td style="width: 32%; vertical-align: top; padding-right: 10px">
            <h3>Por depositar</h3>
            <table class="t">
                <tbody>
                @foreach ($porDepositar as $responsable => $monto)
                    <tr><td>{{ $responsable }}</td><td class="n">{{ $n($monto) }}</td></tr>
                @endforeach
                <tr class="total"><td>TOTAL POR DEPOSITAR</td><td class="n">{{ $n($resumenDeposito['por_depositar']) }}</td></tr>
                <tr><td>(−) Asignación de caja chica</td><td class="n">{{ $n($resumenDeposito['caja_chica']) }}</td></tr>
                <tr><td>(−) Planilla</td><td class="n">{{ $n($resumenDeposito['planilla']) }}</td></tr>
                <tr class="total"><td style="font-size: 8.5px">TOTAL A DEPOSITAR</td><td class="n rojo" style="font-size: 8.5px">{{ $n($resumenDeposito['total']) }}</td></tr>
                </tbody>
            </table>
        </td>
        <td style="vertical-align: top">
            <h3>Depósitos realizados</h3>
            <table class="t">
                <thead><tr><th>N°</th><th>RESPONSABLE</th><th>FECHA</th><th>BANCO</th><th>EMPRESA</th><th>QUIÉN / OPERACIÓN</th><th>IMPORTE</th></tr></thead>
                <tbody>
                @forelse ($depositos as $i => $x)
                    <tr>
                        <td class="n">{{ $i + 1 }}</td><td>{{ $x['responsable'] }}</td>
                        <td>{{ $x['fecha'] ? \Illuminate\Support\Carbon::parse($x['fecha'])->format('d/m/Y') : '' }}</td>
                        <td>{{ $x['banco'] }}</td><td>{{ $x['empresa'] }}</td><td>{{ $x['quien'] ?: $x['operacion'] }}</td><td class="n">{{ $n($x['monto']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="vacio">Sin depósitos este día.</td></tr>
                @endforelse
                <tr class="total"><td colspan="6">TOTAL DEPÓSITOS</td><td class="n">{{ $n($depositos->sum('monto')) }}</td></tr>
                </tbody>
            </table>
        </td>
    </tr></table>
</td>
<td style="width: 19%; vertical-align: top">
    <h3>Detalle ventas</h3>
    <table class="t">
        <thead><tr><th>PROD.</th><th>CANT.</th><th>P.U.</th><th>TOTAL</th></tr></thead>
        <tbody>
        @forelse ($porProductoPrecio as $codigo => $p)
            @foreach ($p['filas'] as $x)
                <tr><td style="font-weight: bold">{{ $loop->first ? $codigo : '' }}</td><td class="n">{{ $n($x->cantidad, 0) }}</td><td class="n">{{ $n($x->precio) }}</td><td class="n">{{ $n($x->total) }}</td></tr>
            @endforeach
            <tr class="sub"><td>TOTAL {{ $codigo }}</td><td class="n">{{ $n($p['cantidad'], 0) }}</td><td></td><td class="n">{{ $n($p['total']) }}</td></tr>
        @empty
            <tr><td colspan="4" class="vacio">Sin ventas.</td></tr>
        @endforelse
        <tr class="total"><td>TOTAL</td><td class="n">{{ $n($detallePrecios->sum('cantidad'), 0) }}</td><td></td><td class="n rojo">{{ $n($detallePrecios->sum('total')) }}</td></tr>
        </tbody>
    </table>
</td>
</tr></table>

<div class="pie">Durasol · Llamazul — generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()?->name ?? 'sistema' }}</div>
<script type="text/php">
    if (isset($pdf)) { $pdf->page_text($pdf->get_width() - 80, $pdf->get_height() - 26, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 7, [0.5, 0.5, 0.5]); }
</script>
</body>
</html>
