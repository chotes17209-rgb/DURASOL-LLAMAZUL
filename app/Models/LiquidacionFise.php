<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vales FISE (S/ 20, 30 o 43) que el cliente entrega como parte de pago. */
class LiquidacionFise extends Model
{
    public const VALORES = [20, 30, 43];

    protected $table = 'liquidacion_fises';

    protected $fillable = ['liquidacion_id', 'cliente_id', 'valor', 'cantidad', 'subtotal'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }
}
