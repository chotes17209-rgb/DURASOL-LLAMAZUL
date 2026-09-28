<?php

namespace App\Models;

use App\Enums\CategoriaCaja;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CajaMovimiento extends Model
{
    use Auditable, SoftDeletes;

    public const INGRESO = 'ingreso';

    public const EGRESO = 'egreso';

    protected $table = 'caja_movimientos';

    protected $fillable = ['fecha', 'tipo', 'categoria', 'monto', 'empresa_id', 'descripcion', 'origen_type', 'origen_id', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2', 'categoria' => CategoriaCaja::class];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function origen(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /** Movimientos creados por otro documento (liquidación, depósito) no se editan a mano. */
    public function esAutomatico(): bool
    {
        return $this->origen_type !== null;
    }

    public function montoConSigno(): float
    {
        return $this->tipo === self::EGRESO ? -(float) $this->monto : (float) $this->monto;
    }

    public function auditLabel(): string
    {
        return ucfirst($this->tipo).' '.$this->categoria->label().' S/ '.$this->monto;
    }
}
