<?php

namespace Tests\Feature;

use App\Models\Arqueo;
use App\Models\CajaChicaMovimiento;
use App\Models\CajaMovimiento;
use Tests\TestCase;

class CajaChicaTest extends TestCase
{
    private function movimiento(array $datos): void
    {
        $this->como('caja')->postJson(route('caja.chica.store'), $datos + ['fecha' => '2026-09-26', 'descripcion' => 'X'])->assertOk();
    }

    public function test_caja_chica_repone_desde_caja_general_y_lleva_saldo_como_el_reporte(): void
    {
        $this->movimiento(['fecha' => '2026-09-25', 'tipo' => 'reposicion', 'concepto' => 'Reposición de fondo', 'monto' => 3192.50]);
        $this->movimiento(['tipo' => 'gasto', 'concepto' => 'Mantenimiento de vehículo', 'descripcion' => 'compra de lubricante', 'monto' => 520, 'comprobante' => 'F001-00011025', 'ruc' => '20601224284']);
        $this->movimiento(['tipo' => 'gasto', 'concepto' => 'Peajes', 'monto' => 33]);
        $this->movimiento(['tipo' => 'gasto', 'concepto' => 'Estibador externo', 'monto' => 79.40]);

        // La reposición es un egreso automático de la caja general; los gastos no la tocan.
        $this->assertSame(1, CajaMovimiento::count());
        $this->assertSame('3192.50', CajaMovimiento::first()->monto);
        $this->assertSame('egreso', CajaMovimiento::first()->tipo);
        $this->assertSame('COMPRA DE LUBRICANTE', CajaChicaMovimiento::where('concepto', 'Mantenimiento de vehículo')->value('descripcion'));

        $pagina = $this->como('caja')->get(route('caja.chica.index', ['desde' => '2026-09-26']))->assertOk();
        $pagina->assertSee('3,192.50')->assertSee('2,672.50')->assertSee('2,560.10')->assertSee('632.40');

        foreach (['pdf', 'xlsx'] as $formato) {
            $this->assertSame(200, $this->como('caja')->get(route('caja.chica.index', ['desde' => '2026-09-26', 'formato' => $formato]))->getStatusCode());
        }

        // Eliminar la reposición la quita también de la caja general.
        $this->como('caja')->deleteJson(route('caja.chica.destroy', CajaChicaMovimiento::where('tipo', 'reposicion')->first()))->assertOk();
        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_saldo_inicial_una_sola_vez_y_luego_se_arrastra_del_dia_anterior(): void
    {
        $this->movimiento(['tipo' => 'apertura', 'concepto' => 'Saldo inicial', 'monto' => 3192.50]);
        $this->movimiento(['tipo' => 'gasto', 'concepto' => 'Peajes', 'monto' => 811.40]);

        // La apertura no sale de la caja general.
        $this->assertSame(0, CajaMovimiento::count());
        $this->como('caja')->get(route('caja.chica.index', ['desde' => '2026-09-26']))->assertOk()
            ->assertSee('3,192.50')->assertSee('2,381.10')->assertSee('Apertura de caja chica')->assertDontSee('Registrar saldo inicial');

        // El 27 el saldo inicial es el saldo final del 26.
        $this->como('caja')->get(route('caja.chica.index', ['desde' => '2026-09-27']))->assertOk()
            ->assertSee('2,381.10')->assertSee('Saldo final del día anterior');

        // No se puede registrar un segundo saldo inicial ni movimientos anteriores a él.
        $this->como('caja')->postJson(route('caja.chica.store'), ['fecha' => '2026-09-27', 'tipo' => 'apertura', 'concepto' => 'Saldo inicial', 'descripcion' => 'X', 'monto' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('monto');
        $this->como('caja')->postJson(route('caja.chica.store'), ['fecha' => '2026-09-25', 'tipo' => 'gasto', 'concepto' => 'Peajes', 'descripcion' => 'X', 'monto' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');
    }

    public function test_arqueo_cuenta_denominaciones_y_calcula_diferencia(): void
    {
        $this->movimiento(['tipo' => 'reposicion', 'concepto' => 'Reposición de fondo', 'monto' => 500]);

        $this->como('caja')->postJson(route('caja.arqueos.store'), [
            'fecha' => '2026-09-26', 'caja' => 'chica',
            'cantidades' => ['200.00' => 2, '50.00' => 1, '20.00' => 2, '0.50' => 3, '0.10' => 5],
        ])->assertOk();

        $arqueo = Arqueo::firstOrFail();
        $this->assertSame('492.00', $arqueo->total_contado); // 400 + 50 + 40 + 1.50 + 0.50
        $this->assertSame('500.00', $arqueo->saldo_sistema);
        $this->assertSame('-8.00', $arqueo->diferencia);

        // Se actualiza el mismo arqueo del día; el de caja general va aparte.
        $this->como('caja')->postJson(route('caja.arqueos.store'), ['fecha' => '2026-09-26', 'caja' => 'chica', 'cantidades' => ['100.00' => 5]])->assertOk();
        $this->assertSame(1, Arqueo::count());
        $this->assertSame('0.00', Arqueo::first()->diferencia);

        $this->como('caja')->get(route('caja.arqueos.index', ['fecha' => '2026-09-26', 'caja' => 'chica']))->assertOk()->assertSee('Arqueos registrados');
        $this->assertSame(200, $this->como('caja')->get(route('caja.arqueos.show', [Arqueo::first(), 'formato' => 'pdf']))->getStatusCode());
        $this->como('liquidaciones')->get(route('caja.arqueos.index'))->assertForbidden();
    }
}
