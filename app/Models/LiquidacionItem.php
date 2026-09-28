<?php

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionItem extends Model
{
    protected $table = 'liquidacion_items';

    protected $fillable = [
        'liquidacion_id', 'cliente_id', 'empresa_id', 'producto_id', 'cantidad', 'precio', 'total', 'costo_unitario',
        'vacios_devueltos', 'metodo_pago', 'monto_credito', 'numero_operacion', 'observacion', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'metodo_pago' => MetodoPago::class,
            'precio' => 'decimal:2',
            'total' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'monto_credito' => 'decimal:2',
        ];
    }

    /** Utilidad bruta de la línea: venta menos costo (null si el producto no tiene costo registrado). */
    public function utilidad(): ?float
    {
        return $this->costo_unitario === null ? null : round((float) $this->total - $this->cantidad * (float) $this->costo_unitario, 2);
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** Lo que el cliente pagó en el momento (total menos lo que quedó a crédito). */
    public function montoPagado(): float
    {
        return round((float) $this->total - (float) $this->monto_credito, 2);
    }
}
