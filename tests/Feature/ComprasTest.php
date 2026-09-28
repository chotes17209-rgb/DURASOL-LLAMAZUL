<?php

namespace Tests\Feature;

use App\Models\CompraPlanta;
use App\Models\CuotaCompra;
use App\Models\PrecioCompra;
use App\Services\CompraService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComprasTest extends TestCase
{
    private function compra(string $fecha, int $cantidad, string $codigo = 'S10', string $empresa = 'DURASOL'): void
    {
        CompraPlanta::create(['fecha' => $fecha, 'empresa_id' => $this->empresa($empresa)->id, 'producto_id' => $this->producto($codigo)->id,
            'cantidad' => $cantidad, 'origen' => 'manual']);
    }

    public function test_cuadro_con_cuota_avance_y_comparativa_base_10kg(): void
    {
        $this->compra('2026-08-10', 1000);
        $this->compra('2026-08-11', 20, 'S45');
        $this->compra('2026-09-01', 695);
        $this->compra('2026-09-02', 650);
        $this->compra('2026-09-01', 42, 'S45');
        $this->compra('2026-09-02', 500, 'S10', 'LLAMAZUL');
        CuotaCompra::create(['mes' => '2026-09', 'empresa_id' => $this->empresa()->id, 'producto_id' => $this->producto('S10')->id, 'cantidad' => 2000]);

        $durasol = app(CompraService::class)->cuadro(Carbon::parse('2026-09-01'), $this->empresa()->id);
        $this->assertSame(1345, $durasol['total']['S10']);
        $this->assertSame(['cuota' => 2000, 'avance' => 1345, 'diferencia' => 655, 'porcentaje' => 67.25], $durasol['cuota']['S10']);
        $this->assertSame(1000, $durasol['comparativa']['S10']['anterior']);
        // Base 10 kg: S10 + S45 x 4.5 -> agosto 1000 + 90 = 1090; setiembre 1345 + 189 = 1534.
        $this->assertSame(1090, $durasol['base10']['anterior']);
        $this->assertSame(1534, $durasol['base10']['actual']);

        $global = app(CompraService::class)->cuadro(Carbon::parse('2026-09-01'), null);
        $this->assertSame(1845, $global['total']['S10']);

        $this->como('logistica')->get(route('compras.index', ['mes' => '2026-09']))->assertOk()->assertSee('Cuota del mes')->assertSee('1,345');
        $this->assertSame(200, $this->como('admin')->get(route('compras.index', ['mes' => '2026-09', 'formato' => 'pdf']))->getStatusCode());
    }

    public function test_logistica_registra_compras_sin_ver_precios(): void
    {
        $instalacion = $this->instalacion();
        PrecioCompra::create(['empresa_id' => $this->empresa()->id, 'instalacion_id' => $instalacion->id, 'producto_id' => $this->producto('S10')->id,
            'precio' => 41.30, 'vigente_desde' => '2026-09-01', 'validado' => true]);

        // Logística registra la cantidad; el precio lo pone el sistema aunque intente enviarlo.
        $this->como('logistica')->postJson(route('compras.store'), [
            'fecha' => '2026-09-14', 'empresa_id' => $this->empresa()->id, 'producto_id' => $this->producto('S10')->id,
            'instalacion_id' => $instalacion->id, 'cantidad' => 380, 'precio_unitario' => 1,
        ])->assertOk();
        $this->assertSame('41.30', CompraPlanta::first()->precio_unitario);

        $this->como('logistica')->get(route('compras.index', ['mes' => '2026-09']))->assertOk()->assertDontSee('41.30')->assertDontSee('Precio unit.');
        $this->como('logistica')->get(route('instalaciones.index'))->assertOk()->assertDontSee('41.30');
        $this->como('logistica')->get(route('instalaciones.show', $instalacion))->assertOk()->assertDontSee('41.30')->assertDontSee('Historial de precios');
        $this->como('logistica')->get(route('precios.compra.index'))->assertForbidden();
        $this->como('logistica')->getJson(route('compras.precio', ['empresa_id' => 1, 'producto_id' => 1, 'fecha' => '2026-09-14']))->assertForbidden();

        // Gerencia ve y corrige precios (CRUD del historial).
        $this->como('admin')->get(route('instalaciones.index'))->assertOk()->assertSee('41.30');
        $precio = PrecioCompra::first();
        $this->como('admin')->putJson(route('precios.compra.update', $precio), ['precio' => 41.50, 'vigente_desde' => '2026-09-01', 'validado' => 1])->assertOk();
        $this->assertSame('41.50', $precio->fresh()->precio);
        $this->como('admin')->deleteJson(route('precios.compra.destroy', $precio))->assertOk();
        $this->assertSame(0, PrecioCompra::count());
    }
}
