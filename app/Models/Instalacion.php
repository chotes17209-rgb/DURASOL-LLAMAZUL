<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Instalación de Solgas (código de 8 dígitos) desde la que compra una empresa.
 * Cada instalación tiene su propio precio de compra, chofer y camión designados.
 */
class Instalacion extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'instalaciones';

    protected $fillable = [
        'codigo', 'nombre', 'empresa_id', 'planta', 'responsable', 'placas', 'direccion', 'chofer_id', 'vehiculo_id', 'activo', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function preciosCompra(): HasMany
    {
        return $this->hasMany(PrecioCompra::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('codigo');
    }

    public function nombreMostrar(): string
    {
        return "{$this->codigo} · {$this->nombre}";
    }

    /** @return array<int, string> placas en mayúsculas (el campo admite varias separadas por coma). */
    public function listaPlacas(): array
    {
        return array_values(array_filter(array_map(fn ($p) => mb_strtoupper(trim($p)), preg_split('/[,;\/]+/', (string) $this->placas))));
    }
}
