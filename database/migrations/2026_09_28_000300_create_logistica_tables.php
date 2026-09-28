<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logística = movimiento de masa. Todo cambio de stock queda en
 * stock_movimientos (kardex); guías, despachos, canjes y movimientos
 * manuales son los documentos que generan esas filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Envase vacío que usa el producto (M10 y C10 usan el envase de 10 kg, etc.).
            $table->foreignId('envase_id')->nullable()->after('capacidad_kg')->constrained('productos')->nullOnDelete();
        });

        Schema::create('guias', function (Blueprint $table) {
            $table->id();
            $table->string('numero_guia', 30);
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('instalacion_id')->constrained('instalaciones');
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('chofer_id')->nullable()->constrained('choferes')->nullOnDelete();
            $table->date('fecha_salida')->index();
            $table->date('fecha_recepcion')->nullable();
            $table->string('estado', 20)->default('en_transito')->index();
            // Registros importados del Excel: se guardan como historia pero no mueven stock.
            $table->boolean('historico')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'numero_guia']);
        });

        Schema::create('guia_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guia_id')->constrained('guias')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedInteger('cantidad_guia')->default(0);
            $table->decimal('precio_compra', 10, 2)->default(0);
            // Envío a planta
            $table->unsignedInteger('vacios_enviados')->default(0);
            $table->unsignedInteger('colores_enviados')->default(0);
            $table->unsignedInteger('cambios_enviados')->default(0);
            // Retorno de planta
            $table->unsignedInteger('llenos_recibidos')->default(0);
            $table->unsignedInteger('cambios_repuestos')->default(0);
            $table->unsignedInteger('vacios_rechazados')->default(0);
            $table->unsignedInteger('colores_rechazados')->default(0);
            $table->timestamps();
            $table->unique(['guia_id', 'producto_id']);
        });

        Schema::create('despachos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('chofer_id')->constrained('choferes');
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->unsignedSmallInteger('vuelta')->default(1);
            $table->string('tipo', 20)->default('local');
            $table->string('destino', 100)->nullable();
            $table->string('estado', 20)->default('en_ruta')->index();
            $table->time('hora_salida')->nullable();
            $table->time('hora_retorno')->nullable();
            $table->boolean('historico')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('despacho_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('despacho_id')->constrained('despachos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedInteger('llenos_salida')->default(0);
            $table->unsignedInteger('llenos_retorno')->default(0);
            $table->unsignedInteger('vacios_retorno')->default(0);
            $table->unsignedInteger('colores_retorno')->default(0);
            $table->unsignedInteger('cambios_retorno')->default(0);
            $table->timestamps();
            $table->unique(['despacho_id', 'empresa_id', 'producto_id']);
        });

        Schema::create('canjes', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('contraparte', 100);
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedInteger('colores_entregados')->default(0);
            $table->unsignedInteger('plomos_recibidos')->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('movimientos_stock_manuales', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('tipo', 30);
            $table->string('sentido', 10);
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('estado', 10);
            $table->unsignedInteger('cantidad');
            $table->string('referencia', 120)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_movimientos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            // lleno, vacio (plomo Solgas), color (otras marcas, sirven para canje), cambio (lleno fallado)
            $table->string('estado', 10);
            $table->integer('cantidad');
            $table->string('concepto', 150);
            $table->nullableMorphs('origen');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['producto_id', 'estado', 'empresa_id'], 'stock_mov_saldo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movimientos');
        Schema::dropIfExists('movimientos_stock_manuales');
        Schema::dropIfExists('canjes');
        Schema::dropIfExists('despacho_detalles');
        Schema::dropIfExists('despachos');
        Schema::dropIfExists('guia_detalles');
        Schema::dropIfExists('guias');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('envase_id');
        });
    }
};
