<?php

namespace Tests\Feature;

use App\Models\Liquidacion;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    public function test_reportes_se_descargan_en_pdf_y_excel(): void
    {
        $cliente = $this->cliente();
        $this->como('liquidaciones')->postJson(route('liquidaciones.store'), [
            'fecha_venta' => '2026-09-14', 'fecha_liquidacion' => '2026-09-15', 'chofer_id' => $this->chofer()->id, 'tipo' => 'local',
            'items' => [['cliente_id' => $cliente->id, 'empresa_id' => $this->empresa()->id, 'producto_id' => $this->producto('S10')->id, 'cantidad' => 3, 'precio' => 45, 'metodo_pago' => 'efectivo']],
        ])->assertOk();
        $liquidacion = Liquidacion::firstOrFail();

        $this->como('logistica')->putJson(route('logistica.partes.update', '2026-09-14'), ['filas' => [['bloque' => 'lleno_salida', 'responsable' => 'LOCAL', 's10' => 2]]])->assertOk();

        $rutas = [
            ['admin', route('reportes.liquidacion-diaria', ['fecha' => '2026-09-14'])],
            ['liquidaciones', route('liquidaciones.show', $liquidacion)],
            ['admin', route('reportes.caja-diaria', ['desde' => '2026-09-01', 'hasta' => '2026-09-14'])],
            ['admin', route('reportes.ventas.exportar', ['desde' => '2026-09-01'])],
            ['logistica', route('logistica.partes.reporte', '2026-09-14')],
            ['logistica', route('logistica.stock', ['fecha' => '2026-09-14'])],
        ];
        foreach ($rutas as [$usuario, $url]) {
            $pdf = $this->como($usuario)->get($url.(str_contains($url, '?') ? '&' : '?').'formato=pdf');
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent());

            $xlsx = $this->como($usuario)->get($url.(str_contains($url, '?') ? '&' : '?').'formato=xlsx');
            $xlsx->assertOk();
            $this->assertStringStartsWith('PK', $xlsx->streamedContent());
        }
    }
}
