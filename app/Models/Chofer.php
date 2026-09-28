<?php

namespace App\Models;

use App\Enums\TipoChofer;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chofer extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'choferes';

    protected $fillable = [
        'alias', 'nombre_completo', 'dni', 'telefono', 'tipo', 'licencia', 'licencia_categoria',
        'licencia_vence', 'vehiculo_id', 'activo', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['tipo' => TipoChofer::class, 'licencia_vence' => 'date', 'activo' => 'boolean'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }

    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('alias');
    }

    /** Choferes que venden (tienen clientes y se liquidan). */
    public function scopeVendedores(Builder $query): Builder
    {
        return $query->activos()->whereIn('tipo', [TipoChofer::Local, TipoChofer::Ruta, TipoChofer::Almacen]);
    }

    public function nombreMostrar(): string
    {
        return $this->nombre_completo ? "{$this->alias} — {$this->nombre_completo}" : $this->alias;
    }
}
