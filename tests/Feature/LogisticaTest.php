<?php

namespace Tests\Feature;

use App\Enums\EstadoStock;
use App\Models\Despacho;
use App\Models\Guia;
use App\Services\StockService;
use Tests\TestCase;

class LogisticaTest extends TestCase
{
    private function stock(string $codigo, EstadoStock $estado, ?string $empresa = 'DURASOL'): int
    {
        $empresaId = $empresa ? $this->empresa($empresa)->id : null;

        return app(StockService::class)->saldo($this->producto($codigo)->id, $estado, $empresaId);
    }

    private function stockInicial(string $codigo, string $estado, int $cantidad, ?string $empresa = 'DURASOL'): void
    {
        $this->como('logistica')->postJson(route('logistica.movimientos.store'), [
            'fecha' => '2026-09-24', 'tipo' => 'stock_inicial', 'sentido' => 'entrada', 'estado' => $estado,
            'empresa_id' => $empresa ? $this->empresa($empresa)->id : null, 'producto_id' => $this->producto($codigo)->id, 'cantidad' => $cantidad,
        ])->assertOk();
    }

    public function test_guia_de_planta_controla_el_movimiento_de_masa(): void
    {
        $this->stockInicial('S10', 'vacio', 500, null);
        $this->stockInicial('S10', 'color', 50, null);
        $instalacion = $this->instalacion();
        $instalacion->preciosCompra()->create(['empresa_id' => $instalacion->empresa_id, 'producto_id' => $this->producto('S10')->id, 'precio' => 41.3, 'vigente_desde' => '2026-01-01']);

        // La guía dice 500 pero solo se envían 480 → error si no se acepta la diferencia.
        $datos = [
            'numero_guia' => 'EG07-0001', 'empresa_id' => $instalacion->empresa_id, 'instalacion_id' => $instalacion->id, 'fecha_salida' => '2026-09-25',
            'detalles' => [['producto_id' => $this->producto('S10')->id, 'cantidad_guia' => 500, 'vacios_enviados' => 450, 'colores_enviados' => 30, 'cambios_enviados' => 0]],
        ];
        $this->como('logistica')->postJson(route('logistica.guias.store'), $datos)->assertStatus(422)->assertJsonValidationErrors('detalles');

        $datos['detalles'][0]['vacios_enviados'] = 470;
        $this->como('logistica')->postJson(route('logistica.guias.store'), $datos)->assertOk();
        $guia = Guia::firstOrFail();
        $this->assertSame('41.30', $guia->detalles->first()->precio_compra); // precio de la instalación
        $this->assertSame(30, $this->stock('S10', EstadoStock::Vacio, null));
        $this->assertSame(20, $this->stock('S10', EstadoStock::Color, null));

        // Al volver: la planta rechazó 10 vacíos → entran 490 llenos. Si no cuadra, error.
        $retorno = ['fecha_recepcion' => '2026-09-25', 'detalles' => [$guia->detalles->first()->id => ['llenos_recibidos' => 490, 'vacios_rechazados' => 5]]];
        $this->como('logistica')->postJson(route('logistica.guias.recibir.store', $guia), $retorno)->assertStatus(422);
        $retorno['detalles'][$guia->detalles->first()->id]['vacios_rechazados'] = 10;
        $this->como('logistica')->postJson(route('logistica.guias.recibir.store', $guia), $retorno)->assertOk();

        $this->assertSame(490, $this->stock('S10', EstadoStock::Lleno));
        $this->assertSame(40, $this->stock('S10', EstadoStock::Vacio, null));

        // Anular la guía revierte todo.
        $this->como('logistica')->postJson(route('logistica.guias.anular', $guia), ['motivo' => 'Prueba'])->assertOk();
        $this->assertSame(0, $this->stock('S10', EstadoStock::Lleno));
        $this->assertSame(500, $this->stock('S10', EstadoStock::Vacio, null));
    }

    public function test_despacho_a_chofer_y_retorno(): void
    {
        $this->stockInicial('S10', 'lleno', 300);
        $chofer = $this->chofer();
        $durasol = $this->empresa()->id;
        $s10 = $this->producto('S10')->id;

        // No se puede sacar más de lo que hay.
        $this->como('logistica')->postJson(route('logistica.despachos.store'), [
            'fecha' => '2026-09-25', 'chofer_id' => $chofer->id, 'vuelta' => 1, 'tipo' => 'local',
            'detalles' => [$durasol => [$s10 => ['llenos_salida' => 301]]],
        ])->assertStatus(422)->assertJsonValidationErrors('stock');

        $this->como('logistica')->postJson(route('logistica.despachos.store'), [
            'fecha' => '2026-09-25', 'chofer_id' => $chofer->id, 'vuelta' => 1, 'tipo' => 'local',
            'detalles' => [$durasol => [$s10 => ['llenos_salida' => 120]]],
        ])->assertOk();
        $this->assertSame(180, $this->stock('S10', EstadoStock::Lleno));

        $despacho = Despacho::firstOrFail();
        $detalle = $despacho->detalles->first();
        $this->como('logistica')->postJson(route('logistica.despachos.retorno.store', $despacho), [
            'detalles' => [$detalle->id => ['llenos_retorno' => 15, 'vacios_retorno' => 95, 'colores_retorno' => 8, 'cambios_retorno' => 2]],
        ])->assertOk();

        $despacho->refresh()->load('detalles');
        $this->assertSame(103, $despacho->totalVendidos()); // 120 − 15 − 2
        $this->assertSame(195, $this->stock('S10', EstadoStock::Lleno));
        $this->assertSame(95, $this->stock('S10', EstadoStock::Vacio, null));
        $this->assertSame(8, $this->stock('S10', EstadoStock::Color, null));
        $this->assertSame(2, $this->stock('S10', EstadoStock::Cambio));

        // El cuadre de la liquidación ve lo vendido según logística.
        $this->como('liquidaciones')->getJson(route('liquidaciones.cuadre', ['chofer_id' => $chofer->id, 'fecha' => '2026-09-25']))
            ->assertOk()->assertJson(['S10' => 103]);
    }

    public function test_canje_de_colores_por_plomos(): void
    {
        $this->stockInicial('S10', 'color', 219, null);
        $this->como('logistica')->postJson(route('logistica.canjes.store'), [
            'fecha' => '2026-09-25', 'contraparte' => 'PLANTA MOVIL', 'producto_id' => $this->producto('S10')->id, 'colores_entregados' => 200, 'plomos_recibidos' => 200,
        ])->assertOk();

        $this->assertSame(19, $this->stock('S10', EstadoStock::Color, null));
        $this->assertSame(200, $this->stock('S10', EstadoStock::Vacio, null));
    }

    public function test_vacios_de_masgas_usan_el_envase_de_10_kg(): void
    {
        $this->como('logistica')->postJson(route('logistica.movimientos.store'), [
            'fecha' => '2026-09-24', 'tipo' => 'ingreso_vacios', 'sentido' => 'entrada', 'estado' => 'vacio',
            'producto_id' => $this->producto('M10')->id, 'cantidad' => 7, 'referencia' => 'Ejército',
        ])->assertOk();

        $this->assertSame(7, $this->stock('S10', EstadoStock::Vacio, null));
    }
}
