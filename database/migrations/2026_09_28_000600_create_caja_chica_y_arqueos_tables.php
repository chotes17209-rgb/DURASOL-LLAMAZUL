<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja chica (fondo fijo para gastos menores, repuesto desde caja general)
 * y arqueos de efectivo (conteo de billetes y monedas contra el saldo del sistema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_chica_movimientos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('tipo', 12); // reposicion | gasto
            $table->string('concepto', 60);
            $table->string('descripcion', 255);
            $table->decimal('monto', 12, 2);
            $table->string('comprobante', 40)->nullable();
            $table->string('proveedor', 150)->nullable();
            $table->string('ruc', 11)->nullable();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('chofer_id')->nullable()->constrained('choferes')->nullOnDelete();
            $table->string('observacion', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('arqueos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('caja', 10); // general | chica
            $table->json('detalle');
            $table->decimal('total_contado', 14, 2);
            $table->decimal('saldo_sistema', 14, 2);
            $table->decimal('diferencia', 14, 2);
            $table->string('observaciones', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['fecha', 'caja']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueos');
        Schema::dropIfExists('caja_chica_movimientos');
    }
};
