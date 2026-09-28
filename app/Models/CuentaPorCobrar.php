<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Venta al crédito pendiente de cobro. */
class CuentaPorCobrar extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'cuentas_por_cobrar';

    protected $fillable = ['cliente_id', 'liquidacion_id', 'liquidacion_item_id', 'fecha', 'monto', 'saldo', 'estado', 'observaciones'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2', 'saldo' => 'decimal:2', 'estado' => EstadoCuenta::class];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function cobranzas(): HasMany
    {
        return $this->hasMany(Cobranza::class)->orderBy('fecha');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoCuenta::Pendiente);
    }

    public function diasVencida(): int
    {
        return (int) $this->fecha->diffInDays(today());
    }

    public function auditLabel(): string
    {
        return 'Crédito '.($this->cliente?->nombre ?? '').' '.$this->fecha?->format('d/m/Y');
    }
}
