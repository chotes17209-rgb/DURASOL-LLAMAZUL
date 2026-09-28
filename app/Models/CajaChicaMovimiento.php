<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Movimiento de caja chica: reposición del fondo (ingreso) o gasto menor con su comprobante. */
class CajaChicaMovimiento extends Model
{
    use Auditable, SoftDeletes;

    public const REPOSICION = 'reposicion';

    public const GASTO = 'gasto';

    /** Saldo inicial de la caja chica: se registra una sola vez; luego el saldo pasa de un día al siguiente. */
    public const APERTURA = 'apertura';

    protected $table = 'caja_chica_movimientos';

    protected $fillable = [
        'fecha', 'tipo', 'concepto', 'descripcion', 'monto', 'comprobante', 'proveedor', 'ruc', 'vehiculo_id', 'chofer_id', 'observacion', 'user_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function esReposicion(): bool
    {
        return $this->tipo === self::REPOSICION;
    }

    public function esApertura(): bool
    {
        return $this->tipo === self::APERTURA;
    }

    public function montoConSigno(): float
    {
        return $this->tipo === self::GASTO ? -(float) $this->monto : (float) $this->monto;
    }

    public function auditLabel(): string
    {
        return match ($this->tipo) {
            self::APERTURA => 'Saldo inicial', self::REPOSICION => 'Reposición', default => 'Gasto'
        }.' caja chica S/ '.$this->monto.' '.$this->fecha?->format('d/m/Y');
    }
}
