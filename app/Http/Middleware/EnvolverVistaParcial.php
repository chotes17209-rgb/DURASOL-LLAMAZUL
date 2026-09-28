<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Las fichas y formularios se abren normalmente como ventanas (AJAX). Si alguien entra a su
 * dirección directamente (pestaña nueva, recarga, enlace compartido), se muestran dentro del
 * diseño completo de la aplicación en lugar de HTML sin estilos.
 */
class EnvolverVistaParcial
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson() || ! $request->user()
            || ! $response instanceof Response || $response->getStatusCode() !== 200
            || ! str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            return $response;
        }

        $contenido = (string) $response->getContent();
        if ($contenido === '' || stripos($contenido, '<html') !== false) {
            return $response;
        }

        preg_match('/<h2[^>]*>(.*?)<\/h2>/s', $contenido, $m);
        $titulo = trim(html_entity_decode(strip_tags($m[1] ?? ''))) ?: null;

        return $response->setContent(view('layouts.parcial', ['contenido' => $contenido, 'titulo' => $titulo])->render());
    }
}
