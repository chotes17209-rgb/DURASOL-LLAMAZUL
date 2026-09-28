<?php

namespace App\Services;

use App\Models\Instalacion;
use App\Models\PrecioCompra;
use App\Models\Producto;
use Illuminate\Support\Carbon;

/**
 * Costo unitario de la mercadería vendida.
 *
 * Criterio (costo de reposición a la fecha de la venta):
 *  1. Productos que se compran en planta (S10, S45, M10): promedio de los precios de compra
 *     vigentes ese día en las instalaciones de la empresa que vende.
 *  2. Productos que no se compran en planta (Contigas, envases, reguladores): costo referencial
 *     registrado en el maestro de productos.
 * El costo se guarda en cada línea de venta al registrarla, de modo que un cambio de precio
 * posterior no altera los resultados de periodos ya cerrados.
 */
class CostoService
{
    /** @var array<string, ?float> */
    private array $cache = [];

    public function costoUnitario(int $empresaId, int $productoId, Carbon|string $fecha): ?float
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $clave = "$empresaId|$productoId|$fecha";

        return $this->cache[$clave] ??= $this->calcular($empresaId, $productoId, $fecha);
    }

    private function calcular(int $empresaId, int $productoId, string $fecha): ?float
    {
        $producto = Producto::withTrashed()->find($productoId);
        if (! $producto) {
            return null;
        }

        if ($producto->se_compra_en_planta) {
            $instalaciones = Instalacion::withTrashed()->where('empresa_id', $empresaId)->pluck('id');
            $precios = $instalaciones->map(fn ($id) => PrecioCompra::where('instalacion_id', $id)->where('producto_id', $productoId)
                ->where('vigente_desde', '<=', $fecha)->orderByDesc('vigente_desde')->orderByDesc('id')->value('precio'))
                ->filter(fn ($p) => $p !== null && (float) $p > 0);
            if ($precios->isNotEmpty()) {
                return round($precios->avg(), 2);
            }
        }

        return $producto->costo_referencial !== null ? (float) $producto->costo_referencial : null;
    }
}
