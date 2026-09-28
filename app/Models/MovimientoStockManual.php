<?php

namespace App\Models;

use App\Enums\EstadoStock;
use App\Enums\TipoMovimientoManual;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stock inicial, ingresos de vacíos de clientes, préstamos, mermas y ajustes. */
class MovimientoStockManual extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'movimientos_stock_manuales';

    protected $fillable = [
        'fecha', 'tipo', 'sentido', 'empresa_id', 'producto_id', 'estado', 'cantidad', 'referencia', 'observaciones', 'user_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'tipo' => TipoMovimientoManual::class, 'estado' => EstadoStock::class];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientos(): MorphMany
    {
        return $this->morphMany(StockMovimiento::class, 'origen');
    }

    public function cantidadConSigno(): int
    {
        return $this->sentido === 'salida' ? -$this->cantidad : $this->cantidad;
    }

    public function auditLabel(): string
    {
        return $this->tipo->label().' '.$this->fecha?->format('d/m/Y');
    }
}
