<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas: ->middleware('role:logistica,caja'). El administrador siempre pasa.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            abort(403, 'Usuario inactivo.');
        }

        if (! $user->hasRole(...$roles)) {
            abort(403, 'Tu usuario no tiene acceso a este módulo.');
        }

        return $next($request);
    }
}
