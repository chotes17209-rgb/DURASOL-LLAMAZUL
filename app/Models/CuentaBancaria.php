<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CuentaBancaria extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'cuentas_bancarias';

    protected $fillable = ['banco', 'alias', 'numero', 'empresa_id', 'moneda', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function depositos(): HasMany
    {
        return $this->hasMany(Deposito::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('banco');
    }

    public function nombreMostrar(): string
    {
        return "{$this->banco} · {$this->alias}";
    }

    public function auditLabel(): string
    {
        return $this->nombreMostrar();
    }
}
