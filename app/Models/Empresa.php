<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = ['nombre', 'razon_social', 'ruc', 'direccion', 'telefono', 'color', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function instalaciones(): HasMany
    {
        return $this->hasMany(Instalacion::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('nombre');
    }
}
