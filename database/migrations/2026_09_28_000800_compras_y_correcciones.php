<?php

use App\Support\DatosHistoricos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compras en planta con precio y documento, cuotas mensuales de compra
 * y correcciones de datos importados (fechas mal digitadas, FISE del 25/09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_planta', function (Blueprint $table) {
            $table->foreignId('instalacion_id')->nullable()->after('producto_id')->constrained('instalaciones')->nullOnDelete();
            $table->decimal('precio_unitario', 10, 2)->nullable()->after('cantidad');
            $table->string('documento', 40)->nullable()->after('precio_unitario');
            $table->string('observacion', 255)->nullable()->after('origen');
            $table->foreignId('user_id')->nullable()->after('observacion')->constrained('users')->nullOnDelete();
        });

        Schema::create('cuotas_compra', function (Blueprint $table) {
            $table->id();
            $table->char('mes', 7);
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedInteger('cantidad');
            $table->timestamps();
            $table->unique(['mes', 'empresa_id', 'producto_id']);
        });

        // Base ya cargada con los datos del Excel: se reemplazan las compras por el registro de compras
        // y se corrigen los datos importados.
        if (DB::table('liquidaciones')->where('historico', true)->exists()) {
            DB::table('compras_planta')->where('origen', 'excel')->delete();
            DatosHistoricos::cargarCompras();
            DatosHistoricos::corregirFechasVentas();
            DatosHistoricos::cargarFise25Setiembre();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas_compra');
        Schema::table('compras_planta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instalacion_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['precio_unitario', 'documento', 'observacion']);
        });
    }
};
