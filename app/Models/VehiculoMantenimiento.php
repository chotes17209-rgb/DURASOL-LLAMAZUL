<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehiculoMantenimiento extends Model
{
    use Auditable, SoftDeletes;

    public const TIPOS = ['preventivo' => 'Preventivo', 'correctivo' => 'Correctivo', 'llantas' => 'Llantas', 'aceite' => 'Cambio de aceite', 'otro' => 'Otro'];

    protected $table = 'vehiculo_mantenimientos';

    protected $fillable = [
        'vehiculo_id', 'fecha', 'tipo', 'kilometraje', 'descripcion', 'taller', 'costo',
        'proximo_fecha', 'proximo_kilometraje',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'proximo_fecha' => 'date', 'costo' => 'decimal:2'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function auditLabel(): string
    {
        return 'Mantenimiento '.($this->vehiculo?->placa ?? '').' '.$this->fecha?->format('d/m/Y');
    }
}
