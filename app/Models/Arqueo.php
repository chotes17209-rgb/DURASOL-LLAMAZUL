<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Arqueo de efectivo: conteo por denominación comparado con el saldo del sistema. */
class Arqueo extends Model
{
    use Auditable;

    public const CAJAS = ['general' => 'Caja general', 'chica' => 'Caja chica'];

    protected $fillable = ['fecha', 'caja', 'detalle', 'total_contado', 'saldo_sistema', 'diferencia', 'observaciones', 'user_id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date', 'detalle' => 'array',
            'total_contado' => 'decimal:2', 'saldo_sistema' => 'decimal:2', 'diferencia' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nombreCaja(): string
    {
        return self::CAJAS[$this->caja] ?? $this->caja;
    }

    public function auditLabel(): string
    {
        return 'Arqueo '.$this->nombreCaja().' '.$this->fecha?->format('d/m/Y');
    }
}
