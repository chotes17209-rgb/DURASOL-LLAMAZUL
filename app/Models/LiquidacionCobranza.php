<?php

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Deuda anterior que el cliente pagó al chofer durante el reparto. */
class LiquidacionCobranza extends Model
{
    protected $table = 'liquidacion_cobranzas';

    protected $fillable = ['liquidacion_id', 'cliente_id', 'monto', 'metodo_pago', 'numero_operacion'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'metodo_pago' => MetodoPago::class];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(Cobranza::class);
    }
}
