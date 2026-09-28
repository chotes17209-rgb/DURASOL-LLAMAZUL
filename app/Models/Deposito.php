<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Depósito bancario del efectivo de caja. */
class Deposito extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'depositos';

    protected $fillable = [
        'fecha', 'cuenta_bancaria_id', 'empresa_id', 'chofer_id', 'depositante', 'numero_operacion', 'monto', 'observaciones', 'user_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2'];
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientoCaja(): MorphOne
    {
        return $this->morphOne(CajaMovimiento::class, 'origen');
    }

    public function auditLabel(): string
    {
        return 'Depósito S/ '.$this->monto.' '.$this->fecha?->format('d/m/Y');
    }
}
