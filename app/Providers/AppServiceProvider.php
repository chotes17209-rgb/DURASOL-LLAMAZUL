<?php

namespace App\Providers;

use App\Models;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        Paginator::defaultView('components.pagination');
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);
        // Los IDs de las rutas son numéricos: una URL como /liquidaciones/abc responde 404 y no un error de base de datos.
        foreach (['empresa', 'producto', 'usuario', 'vehiculo', 'documento', 'mantenimiento', 'chofer', 'instalacion',
            'cliente', 'liquidacion', 'cuenta', 'cobranza', 'deposito', 'precio'] as $parametro) {
            Route::pattern($parametro, '[0-9]+');
        }

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Alias cortos y estables para las relaciones polimórficas (historial, kardex, caja).
        Relation::enforceMorphMap([
            'user' => Models\User::class,
            'empresa' => Models\Empresa::class,
            'producto' => Models\Producto::class,
            'vehiculo' => Models\Vehiculo::class,
            'vehiculo_documento' => Models\VehiculoDocumento::class,
            'vehiculo_mantenimiento' => Models\VehiculoMantenimiento::class,
            'chofer' => Models\Chofer::class,
            'instalacion' => Models\Instalacion::class,
            'cuenta_bancaria' => Models\CuentaBancaria::class,
            'cliente' => Models\Cliente::class,
            'precio_compra' => Models\PrecioCompra::class,
            'precio_venta' => Models\PrecioVenta::class,
            'parte' => Models\Parte::class,
            'liquidacion' => Models\Liquidacion::class,
            'cuenta_por_cobrar' => Models\CuentaPorCobrar::class,
            'cobranza' => Models\Cobranza::class,
            'caja_movimiento' => Models\CajaMovimiento::class,
            'deposito' => Models\Deposito::class,
        ]);

        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            AuditLogger::event('login', 'Inicio de sesión', $event->user);
        });
        Event::listen(Logout::class, fn (Logout $event) => $event->user
            ? AuditLogger::event('logout', 'Cierre de sesión', $event->user)
            : null);
        Event::listen(Failed::class, fn (Failed $event) => AuditLogger::event(
            'login_failed',
            'Intento de acceso fallido con usuario "'.($event->credentials['username'] ?? '').'"',
        ));
    }
}
