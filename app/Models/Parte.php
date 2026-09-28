<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parte diario de almacén (hoja "LLENOS" y "VACÍOS" del área de logística).
 */
class Parte extends Model
{
    use Auditable;

    public const ABIERTO = 'abierto';

    public const CERRADO = 'cerrado';

    protected $table = 'partes';

    protected $fillable = ['fecha', 'estado', 'observaciones', 'user_id', 'cerrado_por', 'cerrado_at'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'cerrado_at' => 'datetime'];
    }

    public function filas(): HasMany
    {
        return $this->hasMany(ParteFila::class)->orderBy('orden')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function esEditable(): bool
    {
        return $this->estado !== self::CERRADO;
    }

    public function auditLabel(): string
    {
        return 'Parte diario '.$this->fecha?->format('d/m/Y');
    }
}
