<?php

namespace Tests\Feature;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoLiquidacion;
use App\Models\Audit;
use App\Models\CajaMovimiento;
use App\Models\CuentaPorCobrar;
use App\Models\Liquidacion;
use Tests\TestCase;

class LiquidacionTest extends TestCase
{
    private function payload(array $extra = []): array
    {
        return array_merge([
            'fecha_venta' => '2026-09-24',
            'fecha_liquidacion' => '2026-09-25',
            'chofer_id' => $this->chofer()->id,
            'tipo' => 'local',
            'items' => [],
            'fises' => [],
            'cobranzas' => [],
            'gastos' => [],
        ], $extra);
    }

    public function test_calcula_efectivo_venta_menos_vouchers_fise_y_credito(): void
    {
        $rosa = $this->cliente(['S10' => 45]);
        $pepe = $this->cliente(['S10' => 44, 'S45' => 210], 'PEPE GAS');
        $durasol = $this->empresa()->id;

        $respuesta = $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload([
            'items' => [
                ['cliente_id' => $rosa->id, 'empresa_id' => $durasol, 'producto_id' => $this->producto('S10')->id, 'cantidad' => 10, 'precio' => 45, 'vacios_devueltos' => 10, 'metodo_pago' => 'efectivo', 'es_credito' => false],
                ['cliente_id' => $pepe->id, 'empresa_id' => $durasol, 'producto_id' => $this->producto('S10')->id, 'cantidad' => 5, 'precio' => 44, 'metodo_pago' => 'yape', 'es_credito' => false],
                ['cliente_id' => $pepe->id, 'empresa_id' => $durasol, 'producto_id' => $this->producto('S45')->id, 'cantidad' => 1, 'precio' => 210, 'metodo_pago' => 'efectivo', 'es_credito' => true, 'monto_credito' => 210],
            ],
            'fises' => [['cliente_id' => $rosa->id, 'valor' => 20, 'cantidad' => 2], ['cliente_id' => null, 'valor' => 43, 'cantidad' => 1]],
            'gastos' => [['concepto' => 'Combustible', 'monto' => 30]],
        ]));

        $respuesta->assertOk()->assertJsonStructure(['message', 'redirect', 'cerrarUrl']);
        $liquidacion = Liquidacion::firstOrFail();

        // Venta 450 + 220 + 210 = 880; crédito 210; yape 220; FISE 83; gastos 30 → efectivo 337
        $this->assertSame('880.00', $liquidacion->total_venta);
        $this->assertSame('210.00', $liquidacion->total_credito);
        $this->assertSame('220.00', $liquidacion->total_vouchers);
        $this->assertSame('83.00', $liquidacion->total_fises);
        $this->assertSame('337.00', $liquidacion->efectivo_esperado);
        $this->assertStringStartsWith('LIQ-', $liquidacion->codigo);
    }

    public function test_cerrar_genera_credito_ingreso_a_caja_y_reabrir_lo_revierte(): void
    {
        $cliente = $this->cliente();
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload([
            'items' => [['cliente_id' => $cliente->id, 'empresa_id' => $this->empresa()->id, 'producto_id' => $this->producto('S10')->id, 'cantidad' => 4, 'precio' => 45, 'metodo_pago' => 'efectivo', 'es_credito' => true, 'monto_credito' => 80]],
        ]))->assertOk();
        $liquidacion = Liquidacion::firstOrFail();

        $this->como('caja')->postJson(route('liquidaciones.cerrar.store', $liquidacion), ['efectivo_entregado' => 95])->assertOk();

        $liquidacion->refresh();
        $this->assertSame(EstadoLiquidacion::Cerrada, $liquidacion->estado);
        $this->assertSame('-5.00', $liquidacion->diferencia); // esperaba 100, entregó 95
        $this->assertSame(80.0, (float) CuentaPorCobrar::sum('saldo'));
        $this->assertSame(95.0, (float) CajaMovimiento::where('tipo', 'ingreso')->sum('monto'));
        $this->assertTrue(Audit::where('event', 'cerrada')->exists());

        // Una liquidación cerrada ya no se puede editar.
        $this->como('liquidaciones')->putJson(route('liquidaciones.update', $liquidacion), $this->payload(['items' => []]))->assertStatus(422);

        // Solo el administrador puede reabrir.
        $this->como('caja')->postJson(route('liquidaciones.reabrir', $liquidacion))->assertForbidden();
        $this->como('admin')->postJson(route('liquidaciones.reabrir', $liquidacion))->assertOk();
        $this->assertSame(0, CuentaPorCobrar::count());
        $this->assertSame(0, CajaMovimiento::count());
        $this->assertSame(EstadoLiquidacion::Borrador, $liquidacion->fresh()->estado);
    }

    public function test_precio_vigente_fechas_y_borrador(): void
    {
        $cliente = $this->cliente(['S10' => 45]);
        $item = ['cliente_id' => $cliente->id, 'empresa_id' => $this->empresa()->id, 'producto_id' => $this->producto('S10')->id, 'cantidad' => 2, 'precio' => 1, 'metodo_pago' => 'efectivo'];

        // El precio enviado se ignora: se usa el de su lista de precios.
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['items' => [$item]]))->assertOk();
        $this->assertSame('90.00', Liquidacion::firstOrFail()->total_venta);

        // Sin precio registrado para esa presentación no se puede liquidar.
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['items' => [['producto_id' => $this->producto('S45')->id] + $item]]))
            ->assertStatus(422)->assertJsonValidationErrors('items');

        // Se puede liquidar el mismo día, pero no antes de la venta ni con fechas futuras.
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['fecha_venta' => today()->toDateString(), 'fecha_liquidacion' => today()->toDateString(), 'items' => [$item]]))->assertOk();
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['fecha_venta' => '2026-09-24', 'fecha_liquidacion' => '2026-09-23', 'items' => [$item]]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_liquidacion');
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['fecha_venta' => today()->addDay()->toDateString(), 'fecha_liquidacion' => today()->addDay()->toDateString(), 'items' => [$item]]))
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_venta', 'fecha_liquidacion']);

        // El borrador puede guardarse sin ventas todavía.
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload())->assertOk();
    }

    public function test_cobranza_en_liquidacion_paga_deudas_antiguas_primero(): void
    {
        $cliente = $this->cliente(['S10' => 50]);
        $s10 = $this->producto('S10')->id;
        $durasol = $this->empresa()->id;

        // Día 1: dos créditos (100 y 50)
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['fecha_venta' => '2026-09-20', 'fecha_liquidacion' => '2026-09-21', 'items' => [
            ['cliente_id' => $cliente->id, 'empresa_id' => $durasol, 'producto_id' => $s10, 'cantidad' => 2, 'precio' => 50, 'metodo_pago' => 'efectivo', 'es_credito' => true],
            ['cliente_id' => $cliente->id, 'empresa_id' => $durasol, 'producto_id' => $s10, 'cantidad' => 1, 'precio' => 50, 'metodo_pago' => 'efectivo', 'es_credito' => true],
        ]]))->assertOk();
        $this->como('caja')->postJson(route('liquidaciones.cerrar.store', Liquidacion::first()), ['efectivo_entregado' => 0])->assertOk();

        // Día 2: paga 120 al chofer
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['cobranzas' => [
            ['cliente_id' => $cliente->id, 'monto' => 120, 'metodo_pago' => 'efectivo'],
        ]]))->assertOk();
        $segunda = Liquidacion::latest('id')->first();
        $this->assertSame('120.00', $segunda->efectivo_esperado);
        $this->como('caja')->postJson(route('liquidaciones.cerrar.store', $segunda), ['efectivo_entregado' => 120])->assertOk();

        $cuentas = CuentaPorCobrar::orderBy('id')->get();
        $this->assertSame(EstadoCuenta::Pagada, $cuentas[0]->estado);
        $this->assertSame('30.00', $cuentas[1]->saldo);
    }

    public function test_no_permite_cobrar_mas_que_la_deuda(): void
    {
        $cliente = $this->cliente();
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), $this->payload(['cobranzas' => [
            ['cliente_id' => $cliente->id, 'monto' => 50, 'metodo_pago' => 'efectivo'],
        ]]))->assertOk();

        $this->como('caja')->postJson(route('liquidaciones.cerrar.store', Liquidacion::first()), ['efectivo_entregado' => 50])
            ->assertStatus(422)->assertJsonValidationErrors('monto');
        $this->assertSame(EstadoLiquidacion::Borrador, Liquidacion::first()->estado);
    }

    public function test_valida_datos_obligatorios(): void
    {
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), ['items' => [['cantidad' => 0]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_venta', 'chofer_id', 'items.0.cliente_id', 'items.0.cantidad']);
    }

    public function test_datos_del_cliente_devuelve_precios_vigentes(): void
    {
        $cliente = $this->cliente(['S10' => 45]);
        $cliente->preciosVenta()->create(['producto_id' => $this->producto('S10')->id, 'precio' => 46.5, 'vigente_desde' => '2026-09-01']);

        $this->como('liquidaciones')->getJson(route('liquidaciones.datos-cliente', ['cliente_id' => $cliente->id, 'fecha' => '2026-08-15']))
            ->assertOk()->assertJsonPath('precios.'.$this->producto('S10')->id, 45);
        $this->como('liquidaciones')->getJson(route('liquidaciones.datos-cliente', ['cliente_id' => $cliente->id, 'fecha' => '2026-09-10']))
            ->assertOk()->assertJsonPath('precios.'.$this->producto('S10')->id, 46.5);

        // Búsqueda por código, como el BUSCARV de la hoja REGISTRO.
        $this->como('liquidaciones')->getJson(route('liquidaciones.datos-cliente', ['codigo' => $cliente->codigo, 'fecha' => '2026-09-10']))
            ->assertOk()->assertJsonPath('id', $cliente->id);
        $this->como('liquidaciones')->getJson(route('liquidaciones.datos-cliente', ['codigo' => 999999]))->assertNotFound();
    }
}
