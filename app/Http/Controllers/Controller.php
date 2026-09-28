<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Respuesta estándar de éxito para las acciones AJAX (el front muestra el aviso y recarga las tablas).
     */
    protected function ok(string $message, array $extra = []): JsonResponse
    {
        return response()->json(['message' => $message] + $extra);
    }

    /** Devuelve solo la tabla si la petición es AJAX (filtros/paginación), o la página completa. */
    /** Datos de resumen de la página completa (no se recalculan al filtrar por AJAX). */
    protected function resumen(Request $request, callable $calcular): ?array
    {
        return $request->ajax() ? null : $calcular();
    }

    protected function tableOrPage(Request $request, string $page, string $table, array $data)
    {
        return $request->ajax() ? view($table, $data) : view($page, $data);
    }
}
