<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Depósitos de la hoja de liquidación: dinero que el chofer depositó o transfirió
 * (BCP - Durasol, Yape...). Se descuenta del efectivo que entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_depositos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->string('destino', 100);
            $table->string('numero_operacion', 40)->nullable();
            $table->decimal('monto', 14, 2);
            $table->timestamps();
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->decimal('total_depositos', 14, 2)->default(0)->after('total_gastos');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones', fn (Blueprint $table) => $table->dropColumn('total_depositos'));
        Schema::dropIfExists('liquidacion_depositos');
    }
};
