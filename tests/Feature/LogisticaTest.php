<?php

namespace Tests\Feature;

use App\Models\Parte;
use App\Models\ParteFila;
use App\Services\AlmacenService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LogisticaTest extends TestCase
{
    private function guardar(string $fecha, array $filas): void
    {
        $this->como('logistica')->putJson(route('logistica.partes.update', $fecha), ['filas' => $filas, 'observaciones' => 'ok'])->assertOk();
    }

    public function test_parte_calcula_stock_como_la_hoja_de_logistica(): void
    {
        $chofer = $this->chofer('URBANO');
        $instalacion = $this->instalacion();
        $instalacion->update(['placas' => 'W6D-892, BRU-782']);

        // Stock inicial por conteo del día anterior.
        $this->como('logistica')->postJson(route('logistica.partes.ajuste.store', '2026-09-13'), ['conteo' => ['lleno_s10' => 685, 'plomo_s10' => 1547, 'color_s10' => 749]])->assertOk();

        $this->guardar('2026-09-14', [
            ['bloque' => 'lleno_ingreso', 'placa' => 'w6d-892', 'responsable' => 'abuelo', 'lugar' => 'planta', 'instalacion_id' => $instalacion->id, 'empresa_id' => $instalacion->empresa_id, 's10' => 420],
            ['bloque' => 'lleno_ingreso', 'responsable' => 'urbano', 'lugar' => 'local', 's10' => 7],
            ['bloque' => 'lleno_salida', 'responsable' => 'urbano', 'lugar' => 'local', 's10' => 71],
            ['bloque' => 'vacio_ingreso', 'responsable' => 'urbano', 'lugar' => 'local', 's10' => 58, 'color_s10' => 4],
            ['bloque' => 'vacio_salida', 'placa' => 'W6D-892', 'responsable' => 'ABUELO', 'lugar' => 'PLANTA', 'instalacion_id' => $instalacion->id, 's10' => 380, 'color_s10' => 40],
        ]);

        $control = app(AlmacenService::class)->controlDelDia(Carbon::parse('2026-09-14'));
        $this->assertSame(685, $control['lleno_s10']['inicial']);
        $this->assertSame(685 + 427 - 71, $control['lleno_s10']['final']);
        $this->assertSame(1547 + 58 - 380, $control['plomo_s10']['final']);
        $this->assertSame(749 + 4 - 40, $control['color_s10']['final']);

        // Texto normalizado y chofer vinculado.
        $fila = ParteFila::where('bloque', 'lleno_salida')->first();
        $this->assertSame('URBANO', $fila->responsable);
        $this->assertSame($chofer->id, $fila->chofer_id);

        // Control de masa: 420 vacíos a planta, 420 llenos de planta.
        $masa = app(AlmacenService::class)->controlDeMasa(Parte::where('fecha', '2026-09-14')->first());
        $this->assertSame(0, $masa->first()['diferencia']);

        // Cuadre: salió 71, volvió 7 => vendió 64.
        $cuadre = app(AlmacenService::class)->cuadreChoferes(Carbon::parse('2026-09-14'))->first();
        $this->assertSame(64, $cuadre['productos']['S10']['vendido']);

        $this->como('logistica')->get(route('logistica.partes.show', '2026-09-14'))->assertOk()->assertSee('Ingreso de llenos');
        $this->como('logistica')->get(route('logistica.stock.kardex', ['llave' => 'lleno_s10', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']))->assertOk();
    }

    public function test_parte_cerrado_no_se_modifica_y_admin_lo_reabre(): void
    {
        $this->guardar('2026-09-14', [['bloque' => 'lleno_salida', 'responsable' => 'LOCAL', 's10' => 2]]);
        $this->como('logistica')->postJson(route('logistica.partes.cerrar', '2026-09-14'))->assertOk();

        $this->como('logistica')->putJson(route('logistica.partes.update', '2026-09-14'), ['filas' => []])->assertStatus(422);
        $this->como('logistica')->postJson(route('logistica.partes.reabrir', '2026-09-14'))->assertForbidden();
        $this->como('admin')->postJson(route('logistica.partes.reabrir', '2026-09-14'))->assertOk();
        $this->guardar('2026-09-14', [['bloque' => 'lleno_salida', 'responsable' => 'LOCAL', 's10' => 3]]);
        $this->assertSame(3, (int) ParteFila::sum('s10'));
    }

    public function test_valida_bloque_y_cantidades(): void
    {
        $this->como('logistica')->putJson(route('logistica.partes.update', '2026-09-14'), ['filas' => [['bloque' => 'otro', 's10' => -1]]])
            ->assertStatus(422)->assertJsonValidationErrors(['filas.0.bloque', 'filas.0.s10']);
    }
}
