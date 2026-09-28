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
        'parte' => 'Parte diario de almacén',
        'liquidacion' => 'Liquidación',
        'cuenta_por_cobrar' => 'Cuenta por cobrar',
        'cobranza' => 'Cobranza',
        'caja_movimiento' => 'Movimiento de caja',
        'deposito' => 'Depósito',
        'caja_chica_movimiento' => 'Movimiento de caja chica',
        'arqueo' => 'Arqueo de efectivo',
        'compra_planta' => 'Compra en planta',
    ],

    // Datos de la cabecera del reporte de caja chica.
    'caja_chica' => [
        'sucursal' => env('CAJA_CHICA_SUCURSAL', 'HUANCAYO'),
        'responsable' => env('CAJA_CHICA_RESPONSABLE', 'ZADITH BARTOLO'),
        'conceptos' => [
            'Mantenimiento de vehículo', 'Combustible', 'Peajes', 'Canje de balones', 'Estibador externo',
            'Viáticos', 'Útiles y oficina', 'Servicios', 'Otros',
        ],
    ],

    // Compras: hasta esta fecha las compras vienen del registro importado (no del parte diario).
    'compras' => [
        'importadas_hasta' => env('COMPRAS_IMPORTADAS_HASTA', '2026-09-26'),
    ],

    // Denominaciones de la moneda peruana para el arqueo de efectivo.
    'denominaciones' => [
        'billetes' => [200, 100, 50, 20, 10],
        'monedas' => [5, 2, 1, 0.5, 0.2, 0.1],
    ],

    // Día siguiente a la venta = día en que se liquida.
    'dias_liquidacion' => 1,
];
