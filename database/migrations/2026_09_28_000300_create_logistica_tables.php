<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logística = parte diario de almacén, igual que la hoja que lleva el área:
 *  - LLENOS: ingresos (planta, retornos de choferes, cambios) y salidas (a choferes y clientes de ruta).
 *  - VACÍOS: ingresos (choferes, clientes, canje) y salidas (a planta, canje).
 * El stock de cada día = stock anterior + ingresos − salidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Envase vacío que usa el producto (M10 y C10 usan el envase de 10 kg, etc.).
            $table->foreignId('envase_id')->nullable()->after('capacidad_kg')->constrained('productos')->nullOnDelete();
        });

        Schema::create('partes', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('estado', 10)->default('abierto');
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('parte_filas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parte_id')->constrained('partes')->cascadeOnDelete();
            // lleno_ingreso | lleno_salida | vacio_ingreso | vacio_salida
            $table->string('bloque', 15)->index();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('placa', 30)->nullable();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->string('responsable', 60)->nullable();
            $table->foreignId('chofer_id')->nullable()->constrained('choferes')->nullOnDelete();
            // local, ruta, planta, mina, canje, cambio, ajuste...
            $table->string('lugar', 40)->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('instalacion_id')->nullable()->constrained('instalaciones')->nullOnDelete();
            $table->string('numero_guia', 30)->nullable();
            // Llenos: s10, s45, m10 + cambios. Vacíos: s10/s45 = plomos, color_s10/color_s45 = colores.
            $table->unsignedInteger('s10')->default(0);
            $table->unsignedInteger('s45')->default(0);
            $table->unsignedInteger('m10')->default(0);
            $table->unsignedInteger('cambio_s10')->default(0);
            $table->unsignedInteger('cambio_s45')->default(0);
            $table->unsignedInteger('cambio_m10')->default(0);
            $table->unsignedInteger('color_s10')->default(0);
            $table->unsignedInteger('color_s45')->default(0);
            $table->string('observacion', 200)->nullable();
            $table->timestamps();
            $table->index(['chofer_id', 'bloque']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parte_filas');
        Schema::dropIfExists('partes');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('envase_id');
        });
    }
};
