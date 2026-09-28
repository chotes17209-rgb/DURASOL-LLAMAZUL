<?php

namespace App\Models;

use App\Enums\TipoDocumentoVehicular;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehiculoDocumento extends Model
{
    use Auditable, SoftDeletes;

    /** Días de anticipación para avisar que un documento está por vencer. */
    public const DIAS_ALERTA = 30;

    protected $table = 'vehiculo_documentos';

    protected $fillable = [
        'vehiculo_id', 'tipo', 'numero', 'entidad', 'fecha_emision', 'fecha_vencimiento',
        'costo', 'archivo', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoVehicular::class,
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'costo' => 'decimal:2',
        ];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /** vigente | por_vencer | vencido | sin_fecha */
    public function estadoVencimiento(): string
    {
        if (! $this->fecha_vencimiento) {
            return 'sin_fecha';
        }
        $dias = today()->diffInDays($this->fecha_vencimiento, false);

        return match (true) {
            $dias < 0 => 'vencido',
            $dias <= self::DIAS_ALERTA => 'por_vencer',
            default => 'vigente',
        };
    }

    public function diasParaVencer(): ?int
    {
        return $this->fecha_vencimiento ? (int) today()->diffInDays($this->fecha_vencimiento, false) : null;
    }

    public function auditLabel(): string
    {
        return $this->tipo->label().' '.($this->vehiculo?->placa ?? '');
    }
}
