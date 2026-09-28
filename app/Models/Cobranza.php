<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago aplicado a una cuenta por cobrar. */
class Cobranza extends Model
{
    use Auditable;

    protected $table = 'cobranzas';

    protected $fillable = [
        'cuenta_por_cobrar_id', 'cliente_id', 'fecha', 'monto', 'metodo_pago', 'numero_operacion', 'liquidacion_cobranza_id', 'user_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2', 'metodo_pago' => MetodoPago::class];
    }

    public function cuentaPorCobrar(): BelongsTo
    {
        return $this->belongsTo(CuentaPorCobrar::class)->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function liquidacionCobranza(): BelongsTo
    {
        return $this->belongsTo(LiquidacionCobranza::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLabel(): string
    {
        return 'Cobranza '.($this->cliente?->nombre ?? '').' S/ '.$this->monto;
    }
}
