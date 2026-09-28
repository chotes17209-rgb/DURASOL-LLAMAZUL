<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportesTest extends TestCase
{
    public function test_reportes_se_descargan_en_pdf_y_excel(): void
    {
        $this->como('logistica')->putJson(route('logistica.partes.update', '2026-09-14'), ['filas' => [['bloque' => 'lleno_salida', 'responsable' => 'LOCAL', 's10' => 2]]])->assertOk();

        $rutas = [
            ['admin', route('reportes.liquidacion-diaria', ['fecha' => '2026-09-14'])],
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
