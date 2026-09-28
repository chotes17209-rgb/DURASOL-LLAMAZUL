<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespachoDetalle extends Model
{
    protected $table = 'despacho_detalles';

    protected $fillable = [
        'despacho_id', 'empresa_id', 'producto_id', 'llenos_salida', 'llenos_retorno',
        'vacios_retorno', 'colores_retorno', 'cambios_retorno',
    ];

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Balones vendidos = salieron llenos − regresaron llenos − llenos entregados a
     * cambio de un balón fallado (el cambio no es venta).
     */
    public function vendidos(): int
    {
        return max(0, $this->llenos_salida - $this->llenos_retorno - $this->cambios_retorno);
    }
}
