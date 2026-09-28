<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionDeposito extends Model
{
    protected $table = 'liquidacion_depositos';

    protected $fillable = ['liquidacion_id', 'destino', 'numero_operacion', 'monto'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }
}
