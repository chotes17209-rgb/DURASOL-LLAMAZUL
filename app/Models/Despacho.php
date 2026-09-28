<?php

namespace App\Models;

use App\Enums\EstadoDespacho;
use App\Enums\TipoChofer;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Salida de balones llenos a un chofer (una "vuelta"). Al retornar se registra
 * lo que trae de vuelta: llenos no vendidos, vacíos, vacíos de color y cambios.
 */
class Despacho extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'despachos';

    protected $fillable = [
        'fecha', 'chofer_id', 'vehiculo_id', 'vuelta', 'tipo', 'destino', 'estado', 'hora_salida',
        'hora_retorno', 'historico', 'observaciones', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EstadoDespacho::class,
            'tipo' => TipoChofer::class,
            'historico' => 'boolean',
        ];
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DespachoDetalle::class);
    }

    public function movimientos(): MorphMany
    {
        return $this->morphMany(StockMovimiento::class, 'origen');
    }

    public function totalSalida(): int
    {
        return (int) $this->detalles->sum('llenos_salida');
    }

    public function totalVendidos(): int
    {
        return (int) $this->detalles->sum(fn (DespachoDetalle $d) => $d->vendidos());
    }

    public function totalVacios(): int
    {
        return (int) $this->detalles->sum(fn (DespachoDetalle $d) => $d->vacios_retorno + $d->colores_retorno);
    }

    public function auditLabel(): string
    {
        return 'Despacho '.($this->chofer?->alias ?? '').' '.$this->fecha?->format('d/m/Y').' v'.$this->vuelta;
    }
}
