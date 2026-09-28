<?php

use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Caja\CajaController;
use App\Http\Controllers\Caja\CuentaBancariaController;
use App\Http\Controllers\Caja\DepositoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Flota\ChoferController;
use App\Http\Controllers\Flota\InstalacionController;
use App\Http\Controllers\Flota\VehiculoController;
use App\Http\Controllers\Flota\VehiculoDocumentoController;
use App\Http\Controllers\Flota\VehiculoMantenimientoController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\Logistica\CanjeController;
use App\Http\Controllers\Logistica\DespachoController;
use App\Http\Controllers\Logistica\GuiaController;
use App\Http\Controllers\Logistica\MovimientoManualController;
use App\Http\Controllers\Logistica\StockController;
use App\Http\Controllers\Precios\PrecioCompraController;
use App\Http\Controllers\Precios\PrecioVentaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\Ventas\ClienteController;
use App\Http\Controllers\Ventas\CreditoController;
use App\Http\Controllers\Ventas\LiquidacionController;
use Illuminate\Support\Facades\Route;

// Las rutas "create" y "edit" de los resource usan /crear y /editar (ver AppServiceProvider).

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // Buscador de clientes para los selects (liquidaciones, créditos y precios).
    Route::get('buscar/clientes', [ClienteController::class, 'buscar'])->name('buscar.clientes');

    /* ------------------------------ Administración ------------------------------ */
    Route::middleware('role:admin')->group(function () {
        Route::resource('empresas', EmpresaController::class);
        Route::resource('productos', ProductoController::class);
        Route::resource('usuarios', UsuarioController::class)->parameters(['usuarios' => 'usuario']);
        Route::get('historial', [HistorialController::class, 'index'])->name('historial.index');
        Route::post('liquidaciones/{liquidacion}/reabrir', [LiquidacionController::class, 'reabrir'])->name('liquidaciones.reabrir');
        Route::get('precios/compra/crear', [PrecioCompraController::class, 'create'])->name('precios.compra.create');
        Route::post('precios/compra', [PrecioCompraController::class, 'store'])->name('precios.compra.store');
        Route::delete('precios/compra/{precio}', [PrecioCompraController::class, 'destroy'])->name('precios.compra.destroy');
    });

    /* ------------------------------ Flota, personal y logística ------------------------------ */
    Route::middleware('role:logistica')->group(function () {
        Route::resource('vehiculos', VehiculoController::class);
        Route::get('documentos-vehiculares', [VehiculoDocumentoController::class, 'index'])->name('documentos.index');
        Route::get('vehiculos/{vehiculo}/documentos/crear', [VehiculoDocumentoController::class, 'create'])->name('documentos.create');
        Route::post('vehiculos/{vehiculo}/documentos', [VehiculoDocumentoController::class, 'store'])->name('documentos.store');
        Route::get('documentos-vehiculares/{documento}/archivo', [VehiculoDocumentoController::class, 'archivo'])->name('documentos.archivo');
        Route::resource('documentos-vehiculares', VehiculoDocumentoController::class)
            ->only(['show', 'edit', 'update', 'destroy'])->names('documentos')->parameters(['documentos-vehiculares' => 'documento']);
        Route::get('vehiculos/{vehiculo}/mantenimientos/crear', [VehiculoMantenimientoController::class, 'create'])->name('mantenimientos.create');
        Route::post('vehiculos/{vehiculo}/mantenimientos', [VehiculoMantenimientoController::class, 'store'])->name('mantenimientos.store');
        Route::resource('mantenimientos', VehiculoMantenimientoController::class)
            ->only(['show', 'edit', 'update', 'destroy'])->parameters(['mantenimientos' => 'mantenimiento']);

        Route::resource('choferes', ChoferController::class)->parameters(['choferes' => 'chofer']);
        Route::resource('instalaciones', InstalacionController::class)->parameters(['instalaciones' => 'instalacion']);

        Route::prefix('logistica')->name('logistica.')->group(function () {
            Route::get('stock', [StockController::class, 'index'])->name('stock');
            Route::get('stock/kardex', [StockController::class, 'kardex'])->name('stock.kardex');

            Route::resource('guias', GuiaController::class);
            Route::get('guias/{guia}/recibir', [GuiaController::class, 'recibirForm'])->name('guias.recibir');
            Route::post('guias/{guia}/recibir', [GuiaController::class, 'recibir'])->name('guias.recibir.store');
            Route::post('guias/{guia}/anular', [GuiaController::class, 'anular'])->name('guias.anular');

            Route::resource('despachos', DespachoController::class);
            Route::get('despachos/{despacho}/retorno', [DespachoController::class, 'retornoForm'])->name('despachos.retorno');
            Route::post('despachos/{despacho}/retorno', [DespachoController::class, 'retorno'])->name('despachos.retorno.store');
            Route::post('despachos/{despacho}/anular', [DespachoController::class, 'anular'])->name('despachos.anular');

            Route::resource('canjes', CanjeController::class);
            Route::resource('movimientos', MovimientoManualController::class)->parameters(['movimientos' => 'movimiento']);
        });

        Route::get('precios/compra', [PrecioCompraController::class, 'index'])->name('precios.compra.index');
        Route::get('precios/compra/historial', [PrecioCompraController::class, 'historial'])->name('precios.compra.historial');
    });

    /* ------------------------------ Ventas ------------------------------ */
    Route::middleware('role:liquidaciones,caja')->group(function () {
        Route::resource('clientes', ClienteController::class);

        Route::get('liquidaciones/datos-cliente', [LiquidacionController::class, 'datosCliente'])->name('liquidaciones.datos-cliente');
        Route::get('liquidaciones/cuadre', [LiquidacionController::class, 'cuadre'])->name('liquidaciones.cuadre');
        Route::resource('liquidaciones', LiquidacionController::class)->parameters(['liquidaciones' => 'liquidacion']);
        Route::get('liquidaciones/{liquidacion}/cerrar', [LiquidacionController::class, 'cerrarForm'])->name('liquidaciones.cerrar');
        Route::post('liquidaciones/{liquidacion}/cerrar', [LiquidacionController::class, 'cerrar'])->name('liquidaciones.cerrar.store');
        Route::post('liquidaciones/{liquidacion}/anular', [LiquidacionController::class, 'anular'])->name('liquidaciones.anular');

        Route::get('creditos', [CreditoController::class, 'index'])->name('creditos.index');
        Route::get('creditos/cliente/{cliente}', [CreditoController::class, 'cliente'])->name('creditos.cliente');
        Route::get('creditos/{cuenta}', [CreditoController::class, 'show'])->name('creditos.show');
        Route::get('cobranzas/crear', [CreditoController::class, 'createCobranza'])->name('creditos.cobranzas.create');
        Route::post('cobranzas', [CreditoController::class, 'storeCobranza'])->name('creditos.cobranzas.store');
        Route::delete('cobranzas/{cobranza}', [CreditoController::class, 'destroyCobranza'])->name('creditos.cobranzas.destroy');

        Route::get('reportes/liquidacion-diaria', [ReporteController::class, 'liquidacionDiaria'])->name('reportes.liquidacion-diaria');
        Route::get('reportes/ventas', [ReporteController::class, 'ventas'])->name('reportes.ventas');
        Route::get('reportes/ventas/exportar', [ReporteController::class, 'exportarVentas'])->name('reportes.ventas.exportar');
    });

    /* ------------------------------ Precios de venta ------------------------------ */
    Route::middleware('role:liquidaciones')->prefix('precios/venta')->name('precios.venta.')->group(function () {
        Route::get('/', [PrecioVentaController::class, 'index'])->name('index');
        Route::get('ajuste-masivo', [PrecioVentaController::class, 'ajusteForm'])->name('ajuste');
        Route::post('ajuste-masivo', [PrecioVentaController::class, 'ajuste'])->name('ajuste.store');
        Route::get('historial', [PrecioVentaController::class, 'historial'])->name('historial');
        Route::delete('registro/{precio}', [PrecioVentaController::class, 'destroy'])->name('destroy');
        Route::get('{cliente}', [PrecioVentaController::class, 'show'])->name('show');
        Route::get('{cliente}/editar', [PrecioVentaController::class, 'edit'])->name('edit');
        Route::put('{cliente}', [PrecioVentaController::class, 'update'])->name('update');
    });

    /* ------------------------------ Caja ------------------------------ */
    Route::middleware('role:caja')->group(function () {
        Route::get('caja', [CajaController::class, 'index'])->name('caja.index');
        Route::resource('caja/movimientos', CajaController::class)->except(['index'])
            ->names('caja.movimientos')->parameters(['movimientos' => 'movimiento']);
        Route::resource('caja/depositos', DepositoController::class)->names('caja.depositos')->parameters(['depositos' => 'deposito']);
        Route::resource('cuentas-bancarias', CuentaBancariaController::class)->parameters(['cuentas-bancarias' => 'cuenta']);
        Route::get('reportes/caja-diaria', [ReporteController::class, 'cajaDiaria'])->name('reportes.caja-diaria');
    });
});
