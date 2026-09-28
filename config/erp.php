<?php

return [
    'nombre' => env('APP_NAME', 'Durasol · Llamazul ERP'),

    // Alias corto guardado en la base de datos => nombre legible (historial).
    'modelos' => [
        'user' => 'Usuario',
        'empresa' => 'Empresa',
        'producto' => 'Producto',
        'vehiculo' => 'Vehículo',
        'vehiculo_documento' => 'Documento vehicular',
        'vehiculo_mantenimiento' => 'Mantenimiento',
        'chofer' => 'Chofer',
        'instalacion' => 'Instalación',
        'cuenta_bancaria' => 'Cuenta bancaria',
        'cliente' => 'Cliente',
        'precio_compra' => 'Precio de compra',
        'precio_venta' => 'Precio de venta',
        'guia' => 'Guía de planta',
        'despacho' => 'Despacho a chofer',
        'canje' => 'Canje',
        'movimiento_stock_manual' => 'Movimiento de stock',
        'liquidacion' => 'Liquidación',
        'cuenta_por_cobrar' => 'Cuenta por cobrar',
        'cobranza' => 'Cobranza',
        'caja_movimiento' => 'Movimiento de caja',
        'deposito' => 'Depósito',
    ],

    // Día siguiente a la venta = día en que se liquida.
    'dias_liquidacion' => 1,
];
