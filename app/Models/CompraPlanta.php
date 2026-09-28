<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Compra de balones llenos en planta: día, empresa, presentación, cantidad y precio. */
class CompraPlanta extends Model
{
    use Auditable;

    public const ORIGENES = ['manual' => 'Registro manual', 'parte' => 'Parte diario', 'excel' => 'Importado'];

    protected $table = 'compras_planta';

    protected $fillable = ['fecha', 'empresa_id', 'producto_id', 'instalacion_id', 'cantidad', 'precio_unitario', 'documento', 'origen', 'observacion', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'precio_unitario' => 'decimal:2'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function instalacion(): BelongsTo
    {
        return $this->belongsTo(Instalacion::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importe(): ?float
    {
        return $this->precio_unitario === null ? null : round($this->cantidad * (float) $this->precio_unitario, 2);
    }

    /** Las compras que vienen del parte diario se corrigen en el parte. */
    public function editable(): bool
    {
        return $this->origen !== 'parte';
    }

    public function auditLabel(): string
    {
        return 'Compra '.$this->cantidad.' '.$this->producto?->codigo.' '.$this->fecha?->format('d/m/Y');
    }
}
