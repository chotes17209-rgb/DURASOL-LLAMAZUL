<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\CuentaBancaria;
use App\Models\PrecioVenta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\PrecioService;
use Tests\TestCase;

class ModulosTest extends TestCase
{
    public function test_login_y_bloqueo_de_usuario_inactivo(): void
    {
        $this->post(route('login'), ['username' => 'admin', 'password' => 'demo1234'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(Audit::where('event', 'login')->exists());

        auth()->logout();
        User::where('username', 'caja')->update(['active' => false]);
        $this->post(route('login'), ['username' => 'caja', 'password' => 'demo1234'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_permisos_por_rol(): void
    {
        $this->como('logistica')->get(route('caja.index'))->assertForbidden();
        $this->como('logistica')->get(route('liquidaciones.index'))->assertForbidden();
        $this->como('logistica')->get(route('logistica.partes.index'))->assertOk();

        $this->como('caja')->get(route('logistica.partes.index'))->assertForbidden();
        $this->como('caja')->get(route('caja.index'))->assertOk();
        $this->como('caja')->get(route('usuarios.index'))->assertForbidden();

        $this->como('liquidaciones')->get(route('precios.venta.index'))->assertOk();
        $this->como('liquidaciones')->get(route('precios.compra.index'))->assertForbidden();
        $this->como('logistica')->get(route('precios.compra.index'))->assertOk();
        $this->como('logistica')->get(route('precios.compra.create', ['instalacion_id' => $this->instalacion()->id]))->assertForbidden();

        foreach (['admin', 'logistica', 'caja', 'liquidaciones'] as $usuario) {
            $this->como($usuario)->get(route('dashboard'))->assertOk();
        }
    }

    public function test_crud_de_cliente_con_precios_e_historial(): void
    {
        $s10 = $this->producto('S10')->id;
        $this->como('liquidaciones')->post(route('clientes.store'), [
            'nombre' => 'bodega el eden', 'tipo' => 'local', 'precios' => [$s10 => 45.1], 'activo' => 1,
        ], ['Accept' => 'application/json'])->assertOk();

        $cliente = Cliente::firstOrFail();
        $this->assertSame('BODEGA EL EDEN', $cliente->nombre);
        $this->assertSame(1, PrecioVenta::count());

        // Mismo precio = no crea historial; precio distinto = nueva fila.
        $this->como('liquidaciones')->putJson(route('precios.venta.update', $cliente), ['vigente_desde' => '2026-09-28', 'precios' => [$s10 => 45.1]])->assertOk();
        $this->assertSame(1, PrecioVenta::count());
        $this->como('liquidaciones')->putJson(route('precios.venta.update', $cliente), ['vigente_desde' => '2026-09-28', 'precios' => [$s10 => 45.8], 'motivo' => 'Subida'])->assertOk();
        $this->assertSame(2, PrecioVenta::count());

        $this->como('liquidaciones')->get(route('clientes.show', $cliente), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('45.80');
        $this->assertTrue(Audit::where('auditable_type', 'cliente')->where('event', 'created')->exists());
    }

    public function test_ajuste_masivo_de_precios(): void
    {
        $a = $this->cliente(['S10' => 45]);
        $b = $this->cliente(['S10' => 44], 'OTRO CLIENTE');
        $this->como('liquidaciones')->postJson(route('precios.venta.ajuste.store'), [
            'producto_id' => $this->producto('S10')->id, 'variacion' => 0.7, 'vigente_desde' => today()->toDateString(),
        ])->assertOk()->assertJsonPath('message', 'Precio actualizado para 2 cliente(s).');

        $precios = app(PrecioService::class)->preciosVentaVigentes([$a->id, $b->id]);
        $this->assertSame(45.7, $precios[$a->id][$this->producto('S10')->id]);
        $this->assertSame(44.7, $precios[$b->id][$this->producto('S10')->id]);
    }

    public function test_vehiculo_con_documentos_y_alertas(): void
    {
        $this->como('logistica')->postJson(route('vehiculos.store'), ['placa' => 'avo-836', 'tipo' => 'camion', 'estado' => 'operativo'])->assertOk();
        $vehiculo = Vehiculo::firstOrFail();
        $this->assertSame('AVO-836', $vehiculo->placa);

        $this->como('logistica')->postJson(route('documentos.store', $vehiculo), ['tipo' => 'soat', 'fecha_vencimiento' => today()->addDays(10)->toDateString()])->assertOk();
        $this->como('logistica')->postJson(route('documentos.store', $vehiculo), ['tipo' => 'revision_tecnica', 'fecha_vencimiento' => today()->subDay()->toDateString()])->assertOk();

        $estado = $vehiculo->fresh('documentos')->estadoDocumentos();
        $this->assertSame('por_vencer', $estado['soat']['estado']);
        $this->assertSame('vencido', $estado['revision_tecnica']['estado']);
        $this->assertSame('sin_registro', $estado['dgh']['estado']);

        $this->como('logistica')->get(route('vehiculos.show', $vehiculo), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Vencido');
    }

    public function test_instalacion_requiere_codigo_de_8_digitos(): void
    {
        $this->como('logistica')->postJson(route('instalaciones.store'), ['codigo' => '1234', 'nombre' => 'X', 'empresa_id' => $this->empresa()->id])
            ->assertStatus(422)->assertJsonValidationErrors('codigo');
        $this->como('logistica')->postJson(route('instalaciones.store'), ['codigo' => '12345678', 'nombre' => 'X', 'empresa_id' => $this->empresa()->id])->assertOk();
    }

    public function test_deposito_sale_de_caja(): void
    {
        $cuenta = CuentaBancaria::firstOrFail();
        $this->como('caja')->postJson(route('caja.depositos.store'), ['fecha' => today()->toDateString(), 'cuenta_bancaria_id' => $cuenta->id, 'monto' => 500])->assertOk();

        $this->assertSame(500.0, (float) CajaMovimiento::where('tipo', 'egreso')->where('categoria', 'deposito')->sum('monto'));
        // El movimiento automático no se edita a mano.
        $this->como('caja')->deleteJson(route('caja.movimientos.destroy', CajaMovimiento::first()))->assertStatus(422);
    }

    public function test_pantallas_principales_cargan(): void
    {
        $rutas = ['dashboard', 'logistica.stock', 'logistica.stock.kardex', 'logistica.partes.index',
            'liquidaciones.index', 'liquidaciones.create', 'clientes.index', 'creditos.index', 'precios.compra.index',
            'precios.venta.index', 'caja.index', 'caja.depositos.index', 'reportes.liquidacion-diaria', 'reportes.ventas', 'reportes.caja-diaria',
            'vehiculos.index', 'documentos.index', 'choferes.index', 'instalaciones.index', 'empresas.index', 'productos.index', 'usuarios.index', 'historial.index'];
        foreach ($rutas as $ruta) {
            $this->como('admin')->get(route($ruta))->assertOk();
        }
        $this->como('admin')->get(route('historial.index', ['q' => 'admin']))->assertOk();
    }
}
