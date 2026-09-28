<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Canje de balones vacíos de color (otras marcas) por balones plomo de Solgas. */
class Canje extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'canjes';

    protected $fillable = ['fecha', 'contraparte', 'producto_id', 'colores_entregados', 'plomos_recibidos', 'observaciones', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientos(): MorphMany
    {
        return $this->morphMany(StockMovimiento::class, 'origen');
    }

    public function auditLabel(): string
    {
        return 'Canje '.$this->contraparte.' '.$this->fecha?->format('d/m/Y');
    }
}
