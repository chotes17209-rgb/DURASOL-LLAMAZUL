<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use Auditable, SoftDeletes;

    public const TIPOS = ['local' => 'Local', 'ruta' => 'Ruta', 'institucional' => 'Institucional', 'trabajador' => 'Trabajador'];

    protected $table = 'clientes';

    protected $fillable = [
        'codigo', 'nombre', 'conocido_como', 'documento', 'direccion', 'zona', 'telefono', 'correo',
        'chofer_id', 'tipo', 'limite_credito', 'activo', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'limite_credito' => 'decimal:2'];
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    public function preciosVenta(): HasMany
    {
        return $this->hasMany(PrecioVenta::class);
    }

    public function cuentasPorCobrar(): HasMany
    {
        return $this->hasMany(CuentaPorCobrar::class);
    }

    public function liquidacionItems(): HasMany
    {
        return $this->hasMany(LiquidacionItem::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('nombre');
    }

    public function scopeBuscar(Builder $query, ?string $texto): Builder
    {
        if (blank($texto)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($texto) {
            $q->where('nombre', 'like', "%{$texto}%")
                ->orWhere('conocido_como', 'like', "%{$texto}%")
                ->orWhere('direccion', 'like', "%{$texto}%")
                ->orWhere('documento', 'like', "%{$texto}%");
            if (ctype_digit($texto)) {
                $q->orWhere('codigo', (int) $texto);
            }
        });
    }

    public function deudaPendiente(): float
    {
        return (float) $this->cuentasPorCobrar()->where('estado', EstadoCuenta::Pendiente)->sum('saldo');
    }

    public function nombreMostrar(): string
    {
        return $this->conocido_como ? "{$this->nombre} ({$this->conocido_como})" : $this->nombre;
    }

    public function auditLabel(): string
    {
        return "{$this->codigo} · {$this->nombre}";
    }
}
