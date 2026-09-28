<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidaciones de choferes, cuentas por cobrar, caja, depósitos e historial (auditoría).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->date('fecha_venta')->index();
            $table->date('fecha_liquidacion')->index();
            $table->foreignId('chofer_id')->constrained('choferes');
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->string('tipo', 20)->default('local');
            $table->string('estado', 20)->default('borrador')->index();
            $table->decimal('total_venta', 14, 2)->default(0);
            $table->decimal('total_credito', 14, 2)->default(0);
            $table->decimal('total_vouchers', 14, 2)->default(0);
            $table->decimal('total_fises', 14, 2)->default(0);
            $table->decimal('total_cobranzas', 14, 2)->default(0);
            $table->decimal('total_cobranzas_efectivo', 14, 2)->default(0);
            $table->decimal('total_gastos', 14, 2)->default(0);
            $table->decimal('efectivo_esperado', 14, 2)->default(0);
            $table->decimal('efectivo_entregado', 14, 2)->nullable();
            $table->decimal('diferencia', 14, 2)->default(0);
            $table->boolean('historico')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['chofer_id', 'fecha_venta']);
        });

        Schema::create('liquidacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedInteger('cantidad');
            $table->decimal('precio', 10, 2);
            $table->decimal('total', 14, 2);
            $table->unsignedInteger('vacios_devueltos')->default(0);
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->decimal('monto_credito', 14, 2)->default(0);
            $table->string('numero_operacion', 40)->nullable();
            $table->string('observacion', 200)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->index(['cliente_id', 'producto_id']);
        });

        Schema::create('liquidacion_fises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->decimal('valor', 8, 2);
            $table->unsignedInteger('cantidad');
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });

        Schema::create('liquidacion_gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->string('concepto', 150);
            $table->decimal('monto', 12, 2);
            $table->string('comprobante', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('cuentas_por_cobrar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('liquidacion_id')->nullable()->constrained('liquidaciones')->nullOnDelete();
            $table->foreignId('liquidacion_item_id')->nullable()->constrained('liquidacion_items')->nullOnDelete();
            $table->date('fecha')->index();
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo', 14, 2);
            $table->string('estado', 20)->default('pendiente')->index();
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('liquidacion_cobranzas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->decimal('monto', 14, 2);
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->string('numero_operacion', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('cobranzas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_por_cobrar_id')->constrained('cuentas_por_cobrar')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->date('fecha')->index();
            $table->decimal('monto', 14, 2);
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->string('numero_operacion', 40)->nullable();
            $table->foreignId('liquidacion_cobranza_id')->nullable()->constrained('liquidacion_cobranzas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('tipo', 10);
            $table->string('categoria', 30)->index();
            $table->decimal('monto', 14, 2);
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('descripcion', 255);
            $table->nullableMorphs('origen');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('depositos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('chofer_id')->nullable()->constrained('choferes')->nullOnDelete();
            $table->string('depositante', 100)->nullable();
            $table->string('numero_operacion', 40)->nullable();
            $table->decimal('monto', 14, 2);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30)->index();
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
        Schema::dropIfExists('depositos');
        Schema::dropIfExists('caja_movimientos');
        Schema::dropIfExists('cobranzas');
        Schema::dropIfExists('liquidacion_cobranzas');
        Schema::dropIfExists('cuentas_por_cobrar');
        Schema::dropIfExists('liquidacion_gastos');
        Schema::dropIfExists('liquidacion_fises');
        Schema::dropIfExists('liquidacion_items');
        Schema::dropIfExists('liquidaciones');
    }
};
