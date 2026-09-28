<?php

namespace App\Models;

use App\Enums\EstadoStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Fila del kardex. Solo la escribe App\Services\StockService. */
class StockMovimiento extends Model
{
    protected $table = 'stock_movimientos';

    protected $fillable = ['fecha', 'empresa_id', 'producto_id', 'estado', 'cantidad', 'concepto', 'origen_type', 'origen_id', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'estado' => EstadoStock::class, 'cantidad' => 'integer'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function origen(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
