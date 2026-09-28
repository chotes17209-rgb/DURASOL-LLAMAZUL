<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Precio de compra en planta por instalación y producto (cada cambio es una fila nueva). */
class PrecioCompra extends Model
{
    use Auditable;

    protected $table = 'precios_compra';

    protected $fillable = ['empresa_id', 'instalacion_id', 'producto_id', 'precio', 'vigente_desde', 'motivo', 'validado', 'validado_por', 'validado_at', 'user_id'];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2', 'vigente_desde' => 'date', 'validado' => 'boolean', 'validado_at' => 'datetime'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function instalacion(): BelongsTo
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function auditLabel(): string
    {
        return 'Compra '.($this->producto?->codigo ?? '').' · '.($this->instalacion?->codigo ?? '');
    }
}
