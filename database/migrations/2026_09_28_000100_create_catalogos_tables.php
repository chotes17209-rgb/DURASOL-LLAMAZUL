<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos base: empresas, productos, choferes (personal), vehículos,
 * documentos y mantenimientos vehiculares, instalaciones y cuentas bancarias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('razon_social', 150)->nullable();
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('direccion', 200)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('color', 9)->default('#1d4ed8');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 80);
            $table->string('marca', 40)->nullable();
            $table->unsignedSmallInteger('capacidad_kg')->nullable();
            // gas = balón con gas, envase = balón vacío que se vende, accesorio = regulador, etc.
            $table->string('tipo', 20)->default('gas');
            $table->boolean('se_compra_en_planta')->default(false);
            $table->boolean('controla_stock')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 12)->unique();
            $table->string('tipo', 30)->default('camion');
            $table->string('marca', 40)->nullable();
            $table->string('modelo', 40)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('color', 30)->nullable();
            $table->unsignedInteger('capacidad_balones')->nullable();
            $table->unsignedInteger('kilometraje')->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('estado', 20)->default('operativo');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('choferes', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 40)->unique();
            $table->string('nombre_completo', 150)->nullable();
            $table->string('dni', 12)->nullable();
            $table->string('telefono', 30)->nullable();
            // local = reparte en la ciudad, ruta = viaja a otras ciudades,
            // planta = abastece desde la planta de Solgas, almacen = atiende en el local.
            $table->string('tipo', 20)->default('local');
            $table->string('licencia', 20)->nullable();
            $table->string('licencia_categoria', 10)->nullable();
            $table->date('licencia_vence')->nullable();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehiculo_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('tipo', 30)->index();
            $table->string('numero', 60)->nullable();
            $table->string('entidad', 100)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->decimal('costo', 12, 2)->nullable();
            $table->string('archivo')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehiculo_mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo', 20)->default('preventivo');
            $table->unsignedInteger('kilometraje')->nullable();
            $table->string('descripcion', 255);
            $table->string('taller', 100)->nullable();
            $table->decimal('costo', 12, 2)->default(0);
            $table->date('proximo_fecha')->nullable();
            $table->unsignedInteger('proximo_kilometraje')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('instalaciones', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 8)->unique();
            $table->string('nombre', 100);
            $table->foreignId('empresa_id')->constrained('empresas');
            // Planta Solgas donde se carga (P.HUA, P.LIMA, P.AYACUCHO...).
            $table->string('planta', 40)->nullable();
            // Como figura en el cuadro de logística: chofer o destino (ESPEJO, LIMA...) y placas.
            $table->string('responsable', 60)->nullable();
            $table->string('placas', 80)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->foreignId('chofer_id')->nullable()->constrained('choferes')->nullOnDelete();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->string('banco', 40);
            $table->string('alias', 60);
            $table->string('numero', 40)->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('moneda', 3)->default('PEN');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
        Schema::dropIfExists('instalaciones');
        Schema::dropIfExists('vehiculo_mantenimientos');
        Schema::dropIfExists('vehiculo_documentos');
        Schema::dropIfExists('choferes');
        Schema::dropIfExists('vehiculos');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('empresas');
    }
};
