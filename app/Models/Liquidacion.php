<?php

namespace App\Models;

use App\Enums\EstadoLiquidacion;
use App\Enums\TipoChofer;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Liquidación diaria de un chofer: lo que vendió a cada cliente, cómo le pagaron,
 * los FISE que dejó y cuánto efectivo debe entregar en caja.
 */
class Liquidacion extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'liquidaciones';

    protected $fillable = [
        'codigo', 'fecha_venta', 'fecha_liquidacion', 'chofer_id', 'vehiculo_id', 'tipo', 'estado',
        'total_venta', 'total_credito', 'total_vouchers', 'total_fises', 'total_cobranzas',
        'total_cobranzas_efectivo', 'total_gastos', 'total_depositos', 'efectivo_esperado', 'efectivo_entregado', 'diferencia',
        'historico', 'observaciones', 'user_id', 'cerrada_por', 'cerrada_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'date',
            'fecha_liquidacion' => 'date',
            'estado' => EstadoLiquidacion::class,
            'tipo' => TipoChofer::class,
            'historico' => 'boolean',
            'cerrada_at' => 'datetime',
            'total_venta' => 'decimal:2',
            'total_credito' => 'decimal:2',
            'total_vouchers' => 'decimal:2',
            'total_fises' => 'decimal:2',
            'total_cobranzas' => 'decimal:2',
            'total_cobranzas_efectivo' => 'decimal:2',
            'total_gastos' => 'decimal:2',
            'total_depositos' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_entregado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LiquidacionItem::class)->orderBy('orden')->orderBy('id');
    }

    public function fises(): HasMany
    {
        return $this->hasMany(LiquidacionFise::class);
    }

    /** Efectivo que el chofer debe entregar: lo por depositar menos lo que ya depositó. */
    public function efectivoAEntregar(): float
    {
        return round((float) $this->efectivo_esperado - (float) $this->total_depositos, 2);
    }

    public function depositos(): HasMany
    {
        return $this->hasMany(LiquidacionDeposito::class);
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(LiquidacionGasto::class);
    }

    public function cobranzas(): HasMany
    {
        return $this->hasMany(LiquidacionCobranza::class);
    }

    public function cuentasPorCobrar(): HasMany
    {
        return $this->hasMany(CuentaPorCobrar::class);
    }

    public function esEditable(): bool
    {
        return $this->estado === EstadoLiquidacion::Borrador;
    }

    public function totalBalones(): int
    {
        return (int) $this->items->sum('cantidad');
    }

    public function auditLabel(): string
    {
        return 'Liquidación '.$this->codigo;
    }
}
