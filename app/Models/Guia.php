<?php

namespace App\Models;

use App\Enums\EstadoGuia;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Guía de remisión de una compra en la planta de Solgas.
 * Controla el movimiento de masa: lo que sale vacío debe volver lleno.
 */
class Guia extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'guias';

    protected $fillable = [
        'numero_guia', 'empresa_id', 'instalacion_id', 'vehiculo_id', 'chofer_id', 'fecha_salida',
        'fecha_recepcion', 'estado', 'historico', 'observaciones', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoGuia::class,
            'fecha_salida' => 'date',
            'fecha_recepcion' => 'date',
            'historico' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function instalacion(): BelongsTo
    {
        return $this->belongsTo(Instalacion::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(GuiaDetalle::class);
    }

    public function movimientos(): MorphMany
    {
        return $this->morphMany(StockMovimiento::class, 'origen');
    }

    public function totalGuia(): int
    {
        return (int) $this->detalles->sum('cantidad_guia');
    }

    public function totalEnviado(): int
    {
        return (int) $this->detalles->sum(fn (GuiaDetalle $d) => $d->totalEnviado());
    }

    public function totalRetornado(): int
    {
        return (int) $this->detalles->sum(fn (GuiaDetalle $d) => $d->totalRetornado());
    }

    public function totalLlenos(): int
    {
        return (int) $this->detalles->sum(fn (GuiaDetalle $d) => $d->llenos_recibidos + $d->cambios_repuestos);
    }

    /** Diferencia de masa: lo enviado menos lo que regresó (debe ser cero). */
    public function diferenciaMasa(): int
    {
        return $this->totalEnviado() - $this->totalRetornado();
    }

    public function importeCompra(): float
    {
        return (float) $this->detalles->sum(fn (GuiaDetalle $d) => $d->llenos_recibidos * (float) $d->precio_compra);
    }

    public function auditLabel(): string
    {
        return 'Guía '.$this->numero_guia;
    }
}
