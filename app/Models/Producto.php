<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use Auditable, SoftDeletes;

    public const TIPO_GAS = 'gas';

    public const TIPO_ENVASE = 'envase';

    public const TIPO_ACCESORIO = 'accesorio';

    protected $table = 'productos';

    protected $fillable = [
        'codigo', 'nombre', 'marca', 'capacidad_kg', 'envase_id', 'tipo',
        'se_compra_en_planta', 'costo_referencial', 'controla_stock', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'se_compra_en_planta' => 'boolean',
            'controla_stock' => 'boolean',
            'activo' => 'boolean',
            'capacidad_kg' => 'integer',
        ];
    }

    /** Envase vacío (10 kg o 45 kg) que corresponde a este producto. */
    public function envase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'envase_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('codigo');
    }

    public function scopeDePlanta(Builder $query): Builder
    {
        return $query->activos()->where('se_compra_en_planta', true);
    }

    /** Productos con gas que se entregan llenos (Solgas, Masgas, Contigas). */
    public function scopeGas(Builder $query): Builder
    {
        return $query->activos()->where('tipo', self::TIPO_GAS);
    }

    /** Productos que representan un envase vacío (10 kg y 45 kg). */
    public function scopeEnvases(Builder $query): Builder
    {
        return $query->activos()->whereColumn('envase_id', 'id');
    }

    public function esEnvase(): bool
    {
        return $this->envase_id === $this->id;
    }
}
