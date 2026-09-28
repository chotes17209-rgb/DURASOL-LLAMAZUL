<div class="p-3">
    @include('precios.compra._cuadro', ['modo' => 'instalaciones'])
    @can('ver-precios-compra')<p class="help">En amarillo: precio nuevo pendiente de validar con la factura de Solgas (botón <b>Validar</b>).</p>@endcan
</div>
