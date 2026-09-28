<?php

namespace Tests\Feature;

use App\Models\LiquidacionItem;
use App\Models\PrecioCompra;
use App\Services\RentabilidadService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RentabilidadTest extends TestCase
{
    public function test_estado_de_resultados_con_costo_a_la_fecha_traslados_y_gastos(): void
    {
        // Dos instalaciones de Durasol: el costo del S10 es el promedio de sus precios vigentes.
        foreach ([['11111111', 40.00], ['22222222', 42.00]] as [$codigo, $precio]) {
            PrecioCompra::create(['empresa_id' => $this->empresa()->id, 'instalacion_id' => $this->instalacion('DURASOL', $codigo)->id, 'producto_id' => $this->producto('S10')->id,
                'precio' => $precio, 'vigente_desde' => '2026-09-01', 'validado' => true]);
        }
        $cliente = $this->cliente(['S10' => 45]);
        $almacen = $this->cliente(['S10' => 0], 'ALMACEN HUANCAVELICA');
        $durasol = $this->empresa()->id;
        $s10 = $this->producto('S10')->id;

        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), [
            'fecha_venta' => '2026-09-14', 'fecha_liquidacion' => '2026-09-15', 'chofer_id' => $this->chofer()->id, 'tipo' => 'local',
            'items' => [
                ['cliente_id' => $cliente->id, 'empresa_id' => $durasol, 'producto_id' => $s10, 'cantidad' => 100, 'precio' => 45, 'metodo_pago' => 'efectivo'],
                ['cliente_id' => $almacen->id, 'empresa_id' => $durasol, 'producto_id' => $s10, 'cantidad' => 50, 'precio' => 0, 'metodo_pago' => 'efectivo'],
            ],
            'gastos' => [['concepto' => 'Peaje', 'monto' => 30]],
        ])->assertOk();
        $this->assertSame('41.00', LiquidacionItem::first()->costo_unitario);

        $this->como('caja')->postJson(route('caja.chica.store'), ['fecha' => '2026-09-14', 'tipo' => 'gasto', 'concepto' => 'Peajes', 'descripcion' => 'X', 'monto' => 70])->assertOk();

        $r = app(RentabilidadService::class)->reporte(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'))['resultado'];
        $this->assertSame(4500.0, $r['ventas']);          // el traslado a precio 0 no es venta
        $this->assertSame(4100.0, $r['costo']);           // 100 × 41.00
        $this->assertSame(400.0, $r['utilidad_bruta']);
        $this->assertSame(100.0, $r['total_gastos']);     // 30 varios + 70 caja chica
        $this->assertSame(300.0, $r['utilidad_operativa']);
        $this->assertSame(50, $r['traslados']);

        $this->como('admin')->get(route('reportes.rentabilidad', ['mes' => '2026-09']))->assertOk()->assertSee('Utilidad operativa')->assertSee('4,500.00');
        foreach (['pdf', 'xlsx'] as $formato) {
            $this->assertSame(200, $this->como('admin')->get(route('reportes.rentabilidad', ['mes' => '2026-09', 'formato' => $formato]))->getStatusCode());
        }
        $this->como('caja')->get(route('reportes.rentabilidad'))->assertForbidden();
    }

    public function test_consolidado_fise_por_dia_y_responsable(): void
    {
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), [
            'fecha_venta' => '2026-09-14', 'fecha_liquidacion' => '2026-09-15', 'chofer_id' => $this->chofer('MISAEL')->id, 'tipo' => 'local',
            'fises' => [['cliente_id' => null, 'valor' => 20, 'cantidad' => 3], ['cliente_id' => null, 'valor' => 43, 'cantidad' => 1]],
        ])->assertOk();

        $this->como('caja')->get(route('reportes.fise', ['mes' => '2026-09']))->assertOk()
            ->assertSee('MISAEL')->assertSee('14-sep')->assertSee('103.00');
        $this->assertSame(200, $this->como('caja')->get(route('reportes.fise', ['mes' => '2026-09', 'formato' => 'xlsx']))->getStatusCode());
    }
}
