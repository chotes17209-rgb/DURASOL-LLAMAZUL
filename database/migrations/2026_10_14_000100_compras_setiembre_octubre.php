<?php

use App\Support\DatosHistoricos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registro de compras actualizado: cierre de setiembre (con la corrección del 03/09 de LLAMAZUL)
 * y octubre al 12/10, cuotas de octubre y agosto del 1 al 30 para la comparativa por día.
 * Lo registrado a mano en compras se respeta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('liquidaciones')->where('historico', true)->exists()) {
            DatosHistoricos::recargarCompras();
        }
    }

    public function down(): void
    {
        // Datos de negocio: no se revierten automáticamente.
    }
};
