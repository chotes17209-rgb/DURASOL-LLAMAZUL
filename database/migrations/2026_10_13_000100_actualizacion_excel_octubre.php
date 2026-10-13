<?php

use App\Support\ActualizacionExcel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Excel RENTABILIDAD OCTUBRE11: ventas del 26/09 al 12/10 (y 7 filas del 25/09 que faltaban),
 * variaciones de precio de venta por cliente y FISE/depósitos del 12/10.
 * Solo en una base ya cargada con el Excel; una base nueva lo recibe desde el ExcelSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('liquidaciones')->where('historico', true)->exists()) {
            $resumen = ActualizacionExcel::aplicar('octubre.json');
            Log::info('Actualización con el Excel de octubre', $resumen);
        }
    }

    public function down(): void
    {
        // Datos de negocio: no se revierten automáticamente.
    }
};
