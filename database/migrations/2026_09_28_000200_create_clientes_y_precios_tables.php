<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes y precios. Los precios nunca se sobrescriben: cada cambio es una
 * fila nueva con su "vigente_desde", así queda el historial completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->string('nombre', 150);
            $table->string('conocido_como', 100)->nullable();
            $table->string('documento', 11)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('zona', 80)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 120)->nullable();
            $table->foreignId('chofer_id')->nullable()->constrained('choferes')->nullOnDelete();
            $table->string('tipo', 20)->default('local');
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('nombre');
        });

        Schema::create('precios_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('instalacion_id')->constrained('instalaciones');
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('precio', 10, 2);
            $table->date('vigente_desde');
            $table->string('motivo', 200)->nullable();
            // Validado = la variación de precio ya se refleja en las facturas de Solgas.
            $table->boolean('validado')->default(false);
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['instalacion_id', 'producto_id', 'vigente_desde'], 'precios_compra_vigencia_idx');
        });

        Schema::create('precios_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('precio', 10, 2);
            $table->date('vigente_desde');
            $table->string('motivo', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cliente_id', 'producto_id', 'vigente_desde'], 'precios_venta_vigencia_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_venta');
        Schema::dropIfExists('precios_compra');
        Schema::dropIfExists('clientes');
    }
};
