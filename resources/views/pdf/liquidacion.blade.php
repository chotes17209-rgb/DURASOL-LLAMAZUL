@php
    $n = fn ($v, $d = 2) => (float) $v == 0 ? '-' : number_format((float) $v, $d);
@endphp
@extends('pdf.layouts.hoja', ['tituloHoja' => 'Hoja de liquidación'])

@section('contenido')
<table class="datos">
    <tr>@foreach ($datos as $k => $v)<td class="k">{{ mb_strtoupper($k) }}</td><td class="v">{{ $v }}</td>@endforeach</tr>
</table>

<table style="width: 100%; border-collapse: collapse"><tr>
<td style="vertical-align: top; padding-right: 10px">
    <h3>Registro de ventas</h3>
    <table class="t">
        <thead><tr><th>N°</th><th>CÓDIGO</th><th>CLIENTE</th><th>EMPRESA</th><th>PRES.</th><th>CANT.</th><th>P.U.</th><th>TOTAL</th><th>VACÍOS DEV.</th><th>CRÉDITO</th><th>CONTADO</th><th>PAGO</th><th>N° OP.</th></tr></thead>
        <tbody>
        @forelse ($l->items as $i)
            <tr>
                <td class="n">{{ $loop->iteration }}</td><td>{{ $i->cliente?->codigo }}</td><td>{{ $i->cliente?->nombreMostrar() }}</td><td>{{ $i->empresa?->nombre }}</td>
                <td>{{ $i->producto?->codigo }}</td><td class="n">{{ $n($i->cantidad, 0) }}</td><td class="n">{{ $n($i->precio) }}</td><td class="n rojo">{{ $n($i->total) }}</td>
                <td class="n">{{ $n($i->vacios_devueltos, 0) }}</td><td class="n">{{ $n($i->monto_credito) }}</td><td class="n">{{ $n((float) $i->total - (float) $i->monto_credito) }}</td>
                <td>{{ $i->metodo_pago->label() }}</td><td>{{ $i->numero_operacion }}</td>
            </tr>
        @empty
            <tr><td colspan="13" class="vacio">Sin ventas registradas.</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="5">TOTAL</td><td class="n">{{ $n($l->items->sum('cantidad'), 0) }}</td><td></td><td class="n rojo">{{ $n($l->total_venta) }}</td>
            <td class="n">{{ $n($l->items->sum('vacios_devueltos'), 0) }}</td><td class="n">{{ $n($l->total_credito) }}</td><td class="n">{{ $n((float) $l->total_venta - (float) $l->total_credito) }}</td><td colspan="2"></td>
        </tr>
        </tbody>
    </table>

    <table style="width: 100%; border-collapse: collapse"><tr>
        <td style="width: 50%; vertical-align: top; padding-right: 6px">
            <h3>Cobranzas</h3>
            <table class="t">
                <thead><tr><th>CLIENTE</th><th>PAGO</th><th>MONTO</th></tr></thead>
                <tbody>
                @forelse ($l->cobranzas as $c)
                    <tr><td>{{ $c->cliente?->nombreMostrar() }}</td><td>{{ $c->metodo_pago->label() }}</td><td class="n">{{ $n($c->monto) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="vacio">Sin cobranzas.</td></tr>
                @endforelse
                <tr class="total"><td colspan="2">TOTAL</td><td class="n">{{ $n($l->total_cobranzas) }}</td></tr>
                </tbody>
            </table>

            <h3>Vales FISE</h3>
            <table class="t">
                <thead><tr><th>CLIENTE</th><th>VALOR</th><th>CANT.</th><th>IMPORTE</th></tr></thead>
                <tbody>
                @forelse ($l->fises as $f)
                    <tr><td>{{ $f->cliente?->nombreMostrar() ?? 'General' }}</td><td class="n">{{ $n($f->valor) }}</td><td class="n">{{ $n($f->cantidad, 0) }}</td><td class="n">{{ $n($f->subtotal) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="vacio">Sin vales FISE.</td></tr>
                @endforelse
                <tr class="total"><td colspan="2">TOTAL</td><td class="n">{{ $n($l->fises->sum('cantidad'), 0) }}</td><td class="n">{{ $n($l->total_fises) }}</td></tr>
                </tbody>
            </table>
        </td>
        <td style="vertical-align: top; padding-left: 6px">
            <h3>Varios</h3>
            <table class="t">
                <thead><tr><th>CONCEPTO</th><th>COMPROBANTE</th><th>MONTO</th></tr></thead>
                <tbody>
                @forelse ($l->gastos as $g)
                    <tr><td>{{ $g->concepto }}</td><td>{{ $g->comprobante }}</td><td class="n">{{ $n($g->monto) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="vacio">Sin gastos varios.</td></tr>
                @endforelse
                <tr class="total"><td colspan="2">TOTAL</td><td class="n">{{ $n($l->total_gastos) }}</td></tr>
                </tbody>
            </table>

            <h3>Depósitos</h3>
            <table class="t">
                <thead><tr><th>CUENTA / DESTINO</th><th>N° OPERACIÓN</th><th>MONTO</th></tr></thead>
                <tbody>
                @forelse ($l->depositos as $d)
                    <tr><td>{{ $d->destino }}</td><td>{{ $d->numero_operacion }}</td><td class="n">{{ $n($d->monto) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="vacio">Sin depósitos.</td></tr>
                @endforelse
                <tr class="total"><td colspan="2">TOTAL</td><td class="n">{{ $n($l->total_depositos) }}</td></tr>
                </tbody>
            </table>
        </td>
    </tr></table>
</td>
<td style="width: 24%; vertical-align: top">
    <h3>Resumen</h3>
    <table class="t">
        <tbody>
        @foreach ($resumen as [$concepto, $monto, $resaltar])
            <tr class="{{ $resaltar ? 'total' : '' }} {{ $concepto === 'Efectivo a entregar' ? 'grande' : '' }}">
                <td>{{ mb_strtoupper($concepto) }}</td>
                <td class="n {{ in_array($concepto, ['Venta total', 'Efectivo a entregar']) ? 'rojo' : '' }}">{{ $n($monto) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Detalle por producto</h3>
    <table class="t">
        <thead><tr><th>PROD.</th><th>CANT.</th><th>TOTAL</th><th>VACÍOS</th></tr></thead>
        <tbody>
        @forelse ($porProducto as $codigo => $p)
            <tr><td style="font-weight: bold">{{ $codigo }}</td><td class="n">{{ $n($p['cantidad'], 0) }}</td><td class="n">{{ $n($p['total']) }}</td><td class="n">{{ $n($p['vacios'], 0) }}</td></tr>
        @empty
            <tr><td colspan="4" class="vacio">—</td></tr>
        @endforelse
        <tr class="total">
            <td>TOTAL</td><td class="n">{{ $n(array_sum(array_column($porProducto, 'cantidad')), 0) }}</td>
            <td class="n rojo">{{ $n(array_sum(array_column($porProducto, 'total'))) }}</td><td class="n">{{ $n(array_sum(array_column($porProducto, 'vacios')), 0) }}</td>
        </tr>
        </tbody>
    </table>
</td>
</tr></table>
@endsection
