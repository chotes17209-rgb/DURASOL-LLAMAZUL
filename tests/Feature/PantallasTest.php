<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Recorre todas las pantallas GET sin parámetros con el administrador:
 * ninguna debe responder con error.
 */
class PantallasTest extends TestCase
{
    /** Rutas que necesitan datos en la URL o que no son pantallas. */
    private const OMITIR = ['login', 'logout', 'liquidaciones.datos-cliente', 'liquidaciones.cuadre', 'precios.compra.create', 'logistica.partes.abrir'];

    public function test_todas_las_pantallas_cargan(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && $r->getName() && ! str_contains($r->uri(), '{'))
            ->reject(fn ($r) => in_array($r->getName(), self::OMITIR, true) || str_starts_with($r->uri(), '_') || in_array($r->uri(), ['up', 'storage/{path}'], true));

        $this->assertGreaterThan(30, $rutas->count());
        foreach ($rutas as $ruta) {
            $respuesta = $this->como('admin')->get('/'.ltrim($ruta->uri(), '/'), ['X-Requested-With' => 'XMLHttpRequest']);
            $this->assertLessThan(400, $respuesta->getStatusCode(), "La ruta {$ruta->getName()} ({$ruta->uri()}) respondió {$respuesta->getStatusCode()}");

            $pagina = $this->como('admin')->get('/'.ltrim($ruta->uri(), '/'));
            $this->assertLessThan(400, $pagina->getStatusCode(), "La página {$ruta->getName()} respondió {$pagina->getStatusCode()}");
        }
    }
}
