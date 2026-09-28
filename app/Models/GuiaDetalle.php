<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuiaDetalle extends Model
{
    protected $table = 'guia_detalles';

    protected $fillable = [
        'guia_id', 'producto_id', 'cantidad_guia', 'precio_compra', 'vacios_enviados', 'colores_enviados',
        'cambios_enviados', 'llenos_recibidos', 'cambios_repuestos', 'vacios_rechazados', 'colores_rechazados',
    ];

    protected function casts(): array
    {
        return ['precio_compra' => 'decimal:2'];
    }

    public function guia(): BelongsTo
    {
        return $this->belongsTo(Guia::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function totalEnviado(): int
    {
        return $this->vacios_enviados + $this->colores_enviados + $this->cambios_enviados;
    }

    public function totalRetornado(): int
    {
        return $this->llenos_recibidos + $this->cambios_repuestos + $this->vacios_rechazados + $this->colores_rechazados;
    }
}
