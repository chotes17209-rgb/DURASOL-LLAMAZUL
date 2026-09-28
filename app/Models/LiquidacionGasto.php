<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionGasto extends Model
{
    protected $table = 'liquidacion_gastos';

    protected $fillable = ['liquidacion_id', 'concepto', 'monto', 'comprobante'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }
}
