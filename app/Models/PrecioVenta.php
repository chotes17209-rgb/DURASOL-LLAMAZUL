<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Precio de venta por cliente y producto (cada cambio es una fila nueva = historial). */
class PrecioVenta extends Model
{
    use Auditable;

    protected $table = 'precios_venta';

    protected $fillable = ['cliente_id', 'producto_id', 'precio', 'vigente_desde', 'motivo', 'user_id'];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2', 'vigente_desde' => 'date'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLabel(): string
    {
        return 'Venta '.($this->producto?->codigo ?? '').' · '.($this->cliente?->nombre ?? '');
    }
}
