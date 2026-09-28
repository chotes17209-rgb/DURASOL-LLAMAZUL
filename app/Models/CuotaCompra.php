<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cuota mensual de compra (meta con Solgas) por empresa y presentación. */
class CuotaCompra extends Model
{
    protected $table = 'cuotas_compra';

    protected $fillable = ['mes', 'empresa_id', 'producto_id', 'cantidad'];
}
