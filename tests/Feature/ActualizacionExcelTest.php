<?php

namespace Tests\Feature;

use App\Models\CuentaPorCobrar;
use App\Models\Deposito;
use App\Models\Liquidacion;
use App\Models\PrecioCompra;
use App\Models\PrecioVenta;
use App\Services\CostoService;
use App\Support\ActualizacionExcel;
use Tests\TestCase;

class ActualizacionExcelTest extends TestCase
{
    public function test_actualiza_ventas_precios_fise_y_depositos_sin_duplicar_ni_tocar_lo_manual(): void
    {
        $cliente = $this->cliente(['S10' => 44.10]);
        $this->chofer('JORGE');
        // URBANO ya liquidó el 27/09 en el sistema: esa liquidación no se toca.
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), [
            'fecha_venta' => '2026-09-27', 'fecha_liquidacion' => '2026-09-28', 'chofer_id' => $this->chofer()->id, 'tipo' => 'local',
            'items' => [['cliente_id' => $cliente->id, 'empresa_id' => $this->empresa()->id, 'producto_id' => $this->producto('S10')->id, 'cantidad' => 2, 'precio' => 1, 'metodo_pago' => 'efectivo']],
        ])->assertOk();
        // Dos instalaciones en el precio general (194.50) y una especial: la variación solo va a las primeras.
        foreach ([['11111111', 194.50], ['22222222', 194.50], ['33333333', 180.00]] as [$codigo, $precio]) {
            PrecioCompra::create(['empresa_id' => $this->empresa()->id, 'instalacion_id' => $this->instalacion('DURASOL', $codigo)->id,
                'producto_id' => $this->producto('S45')->id, 'precio' => $precio, 'vigente_desde' => '2026-09-01', 'validado' => true]);
        }

        $c = $cliente->codigo;
        $archivo = tempnam(sys_get_temp_dir(), 'excel');
        file_put_contents($archivo, json_encode([
            'fuente' => 'PRUEBA',
            'ventas' => [
                // fecha, fecha_liq, empresa, código, placa, responsable, cliente, presentación, cantidad, precio, total, balones, crédito, cobranza, costo
                ['2026-09-26', null, 'DURASOL', $c, 'LOCAL', 'JORGE', $cliente->nombre, 'S10', 10, 45.6, 456, 10, 200, null, 41.3],
                ['2026-09-28', null, 'DURASOL', $c, 'LOCAL', 'JORGE', $cliente->nombre, null, null, null, 0, null, null, 150, null],
                ['2026-09-27', null, 'DURASOL', $c, 'LOCAL', 'URBANO', $cliente->nombre, 'S10', 99, 45.6, 4514.4, 99, null, null, 41.3],
            ],
            'precios_venta' => [[$c, 'S10', 45.6, '2026-09-26']],
            'precios_compra' => [['DURASOL', 'S45', 201.4, '2026-09-25']],
            'resumen_dia' => '2026-09-28',
            'fise_resumen' => ['JORGE' => 86],
            'depositos' => [['2026-09-28', 'JORGE', 'BCP', 'DURASOL', 'JORGE', 300.0]],
        ]));

        $resumen = ActualizacionExcel::aplicar($archivo);
        $this->assertSame(['2026-09-27 URBANO'], $resumen['omitidas_manual']);
        $this->assertSame(2, $resumen['liquidaciones']);

        $jorge = Liquidacion::where('chofer_id', $this->chofer('JORGE')->id)->orderBy('fecha_venta')->get();
        $this->assertSame(['456.00', '200.00'], [$jorge[0]->total_venta, $jorge[0]->total_credito]);
        $this->assertSame('41.30', $jorge[0]->items->first()->costo_unitario);
        // La cobranza del 28/09 paga parte del crédito del 26/09; el FISE del día va a su liquidación.
        $this->assertSame('50.00', CuentaPorCobrar::first()->saldo);
        $this->assertSame(['150.00', '86.00'], [$jorge[1]->total_cobranzas, $jorge[1]->total_fises]);
        $this->assertSame(1, Deposito::count());
        // La liquidación manual de URBANO sigue igual.
        $this->assertSame(1, Liquidacion::where('chofer_id', $this->chofer()->id)->first()->items()->count());

        // Precio de venta desde la fecha de la variación; el anterior queda en el historial.
        $this->assertSame(2, PrecioVenta::where('cliente_id', $cliente->id)->count());
        // Precio de compra: 201.40 en las instalaciones del precio general; la especial no cambia y manda el general.
        $this->assertSame(2, PrecioCompra::where('precio', 201.40)->count());
        $this->assertSame(201.4, app(CostoService::class)->costoUnitario($this->empresa()->id, $this->producto('S45')->id, '2026-10-01'));

        // Aplicarlo de nuevo no duplica nada.
        $otra = ActualizacionExcel::aplicar($archivo);
        unlink($archivo);
        $this->assertSame([0, 0, 0, 0, 0, 0], [$otra['liquidaciones'], $otra['filas'], $otra['precios'], $otra['precios_compra'], $otra['fise'], $otra['depositos']]);
        $this->assertSame(3, Liquidacion::count());
    }
}
