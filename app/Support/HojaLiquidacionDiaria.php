<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hoja de liquidación diaria con el formato del Excel de la empresa (RESUMEN GNRAL): todo en una sola hoja,
 * ventas liquidadas y de ruta, por depositar y depósitos a la izquierda; detalle de ventas por precio a la derecha.
 */
class HojaLiquidacionDiaria
{
    public const IMPORTES = ['venta' => 'Venta total', 'cobranza' => 'Cobranza', 'credito' => 'Crédito', 'varios' => 'Varios', 'fise' => 'FISE', 'vouchers' => 'Vouchers', 'depositos' => 'Depósitos', 'por_depositar' => 'Por depositar'];

    public function __construct(private readonly Carbon $fecha, private readonly array $d) {}

    /** Grupo de marca de cada presentación, como en la cabecera del Excel. */
    public static function grupos($productos): array
    {
        $grupos = [];
        foreach ($productos as $p) {
            $g = match (true) {
                str_starts_with($p->codigo, 'C') => 'CONTIGAS',
                str_starts_with($p->codigo, 'K') => 'VACÍOS',
                default => 'SOLGAS',
            };
            $grupos[$g][] = $p->codigo;
        }

        return $grupos;
    }

    public function descargar(string $formato): Response
    {
        $archivo = 'hoja-liquidacion-'.$this->fecha->toDateString();
        AuditLogger::event('exportacion', 'Descargó la hoja de liquidación diaria del '.$this->fecha->format('d/m/Y').' en '.strtoupper($formato), null, ['archivo' => $archivo]);

        return $formato === 'xlsx' ? $this->excel($archivo) : $this->pdf($archivo);
    }

    private function pdf(string $archivo): Response
    {
        $logo = fn (string $f) => 'data:image/jpeg;base64,'.base64_encode((string) @file_get_contents(public_path('img/'.$f)));

        return Pdf::loadView('pdf.hoja-liquidacion', $this->d + [
            'fecha' => $this->fecha,
            'logos' => [$logo('durasol.jpg'), $logo('llamazul.jpg')],
        ])->setOption('isPhpEnabled', true)->setPaper('a4', 'landscape')->download($archivo.'.pdf');
    }

    /* ------------------------------------------------------------------ Excel */

    private const AZUL = 'BDD7EE';

    private const ROJO = 'C00000';

    private function excel(string $archivo): Response
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator(config('app.name'))->setTitle('Hoja de liquidación diaria');
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->hoja($libro->getActiveSheet());

        return response()->streamDownload(fn () => (new Xlsx($libro))->save('php://output'), $archivo.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Logos, título y fecha comunes a las tres hojas; devuelve la fila siguiente. */
    private function cabecera(Worksheet $h, string $titulo, int $ultimaColumna): int
    {
        $fin = Coordinate::stringFromColumnIndex($ultimaColumna);
        foreach (['durasol.jpg' => 'A1', 'llamazul.jpg' => 'C1'] as $img => $celda) {
            if (is_file(public_path('img/'.$img))) {
                (new Drawing)->setPath(public_path('img/'.$img))->setHeight(32)->setCoordinates($celda)->setOffsetY(3)->setWorksheet($h);
            }
        }
        $h->getRowDimension(1)->setRowHeight(30);
        $h->mergeCells("A2:{$fin}2");
        $h->setCellValue('A2', $titulo);
        $h->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
        $h->getRowDimension(2)->setRowHeight(22);
        $h->setCellValue('A4', 'FECHA');
        $h->mergeCells('B4:C4');
        $h->setCellValue('B4', $this->fecha->format('d/m/Y'));
        $h->getStyle('A4')->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL), 'borders' => $this->bordes()]);
        $h->getStyle('B4:C4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12], 'fill' => $this->relleno('FFFF00'), 'borders' => $this->bordes(),
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $h->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(0);
        $h->getPageMargins()->setLeft(0.3)->setRight(0.3)->setTop(0.4)->setBottom(0.4);

        return 6;
    }

    private function hoja(Worksheet $h): void
    {
        $h->setTitle('Liquidación '.$this->fecha->format('d-m-Y'));
        $grupos = self::grupos($this->d['productos']);
        $codigos = array_merge(...array_values($grupos));
        $nCols = 2 + count($codigos) + count(self::IMPORTES) + 1;
        $colDetalle = $nCols + 2; // una columna de separación y luego "Detalle ventas"
        $fila = $this->cabecera($h, 'HOJA DE LIQUIDACIÓN DIARIA', $nCols);

        foreach ($this->d['grupos'] as $i => $g) {
            $fila = $this->tablaGrupo($h, $fila, $g, $i === 1, $grupos, $codigos) + 1;
        }

        // Por depositar (izquierda) y depósitos realizados (debajo de los importes).
        $fin = $this->porDepositar($h, $fila);
        $finDep = $this->depositos($h, $fila, 3 + count($codigos));
        $this->detalle($h, 6, $colDetalle);

        $h->getColumnDimension('A')->setWidth(13);
        $h->getColumnDimension('B')->setWidth(16);
        for ($c = 3; $c <= $nCols; $c++) {
            $h->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth($c <= 2 + count($codigos) ? 8 : 13);
        }
        $h->getColumnDimension(Coordinate::stringFromColumnIndex($nCols + 1))->setWidth(3);
        foreach ([12, 9, 10, 13] as $k => $ancho) {
            $h->getColumnDimension(Coordinate::stringFromColumnIndex($colDetalle + $k))->setWidth($ancho);
        }
        $h->getPageSetup()->setFitToHeight(1);
        $this->pie($h, max($fin, $finDep) + 2);
    }

    private function tablaGrupo(Worksheet $h, int $fila, array $g, bool $esRuta, array $grupos, array $codigos): int
    {
        $fila = $this->titulo($h, $fila, mb_strtoupper($g['titulo']));
        // Cabecera en dos filas: marca arriba, presentaciones abajo.
        $h->setCellValue("A{$fila}", $esRuta ? 'FECHA SALIDA' : 'PLACA');
        $h->setCellValue("B{$fila}", 'RESPONSABLE');
        $h->mergeCells("A{$fila}:A".($fila + 1));
        $h->mergeCells("B{$fila}:B".($fila + 1));
        $c = 3;
        foreach ($grupos as $nombre => $lista) {
            $ini = Coordinate::stringFromColumnIndex($c);
            $h->setCellValue("{$ini}{$fila}", $nombre);
            if (count($lista) > 1) {
                $h->mergeCells("{$ini}{$fila}:".Coordinate::stringFromColumnIndex($c + count($lista) - 1).$fila);
            }
            foreach ($lista as $k => $codigo) {
                $h->setCellValue(Coordinate::stringFromColumnIndex($c + $k).($fila + 1), $codigo);
            }
            $c += count($lista);
        }
        foreach (self::IMPORTES as $clave => $texto) {
            $col = Coordinate::stringFromColumnIndex($c++);
            $h->setCellValue("{$col}{$fila}", mb_strtoupper($esRuta && $clave === 'por_depositar' ? 'Saldo' : $texto));
            $h->mergeCells("{$col}{$fila}:{$col}".($fila + 1));
        }
        $fin = Coordinate::stringFromColumnIndex($c);
        $h->setCellValue("{$fin}{$fila}", 'ESTADO');
        $h->mergeCells("{$fin}{$fila}:{$fin}".($fila + 1));
        $h->getStyle("A{$fila}:{$fin}".($fila + 1))->applyFromArray($this->estiloCabecera());
        $inicio = $fila;
        $fila += 2;

        foreach ($g['filas'] as $f) {
            $valores = [$esRuta ? $f['fecha_venta']->format('d/m/Y') : $f['placa'], $f['responsable']];
            foreach ($codigos as $codigo) {
                $valores[] = $f['cantidades'][$codigo] ?? 0;
            }
            foreach (array_keys(self::IMPORTES) as $clave) {
                $valores[] = $f[$clave];
            }
            $valores[] = $f['liquidacion']->estado->label();
            $h->fromArray($valores, null, "A{$fila}", true);
            $fila++;
        }
        if (! $g['filas']) {
            $h->setCellValue("A{$fila}", 'Sin liquidaciones para esta fecha.');
            $h->mergeCells("A{$fila}:{$fin}{$fila}");
            $fila++;
        }
        $t = $g['total'];
        $h->fromArray(array_merge(['TOTALES', ''], array_map(fn ($cd) => $t['cantidades'][$cd] ?? 0, $codigos), array_map(fn ($k) => $t[$k], array_keys(self::IMPORTES)), ['']), null, "A{$fila}", true);
        $h->getStyle("A{$fila}:{$fin}{$fila}")->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL)]);
        $this->formatoNumeros($h, $inicio + 2, $fila, 3, 2 + count($codigos), '#,##0;-#,##0;"-"');
        $this->formatoNumeros($h, $inicio + 2, $fila, 3 + count($codigos), 2 + count($codigos) + count(self::IMPORTES), '#,##0.00;-#,##0.00;"-"');
        // Venta total y por depositar en rojo, como en la hoja original.
        foreach (['venta', 'por_depositar'] as $k) {
            $col = Coordinate::stringFromColumnIndex(3 + count($codigos) + array_search($k, array_keys(self::IMPORTES), true));
            $h->getStyle("{$col}".($inicio + 2).":{$col}{$fila}")->getFont()->setBold(true)->getColor()->setRGB(self::ROJO);
        }
        $h->getStyle("A{$inicio}:{$fin}{$fila}")->applyFromArray(['borders' => $this->bordes()]);

        return $fila + 1;
    }

    /** Por depositar por responsable, caja chica, planilla y total a depositar (columnas A-D). */
    private function porDepositar(Worksheet $h, int $fila): int
    {
        $fila = $this->titulo($h, $fila, 'POR DEPOSITAR');
        $inicio = $fila;
        $r = $this->d['resumenDeposito'];
        $lineas = [];
        foreach ($this->d['porDepositar'] as $responsable => $monto) {
            $lineas[] = [$responsable, $monto, false];
        }
        array_push($lineas, ['TOTAL POR DEPOSITAR', $r['por_depositar'], true], ['(−) ASIGNACIÓN DE CAJA CHICA', $r['caja_chica'], false],
            ['(−) PLANILLA', $r['planilla'], false], ['TOTAL A DEPOSITAR', $r['total'], true]);
        foreach ($lineas as [$texto, $monto, $resaltar]) {
            $h->setCellValue("A{$fila}", $texto);
            $h->mergeCells("A{$fila}:B{$fila}");
            $h->setCellValue("C{$fila}", $monto);
            $h->mergeCells("C{$fila}:D{$fila}");
            if ($resaltar) {
                $h->getStyle("A{$fila}:D{$fila}")->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL)]);
            }
            $fila++;
        }
        $h->getStyle('C'.($fila - 1))->getFont()->setSize(12)->getColor()->setRGB(self::ROJO);
        $this->formatoNumeros($h, $inicio, $fila - 1, 3, 3, '#,##0.00;-#,##0.00;"-"');
        $h->getStyle("A{$inicio}:D".($fila - 1))->applyFromArray(['borders' => $this->bordes()]);

        return $fila;
    }

    /** Depósitos realizados, a la derecha del bloque "por depositar". */
    private function depositos(Worksheet $h, int $fila, int $col): int
    {
        $c = fn (int $k) => Coordinate::stringFromColumnIndex($col + $k);
        $h->setCellValue($c(0).$fila, 'DEPÓSITOS REALIZADOS');
        $h->getStyle($c(0).$fila)->getFont()->setBold(true);
        $fila++;
        $h->fromArray(['N°', 'RESPONSABLE', 'FECHA', 'BANCO', 'EMPRESA', 'QUIÉN / OPERACIÓN', 'IMPORTE'], null, $c(0).$fila);
        $h->getStyle($c(0).$fila.':'.$c(6).$fila)->applyFromArray($this->estiloCabecera());
        $inicio = $fila++;
        foreach ($this->d['depositos'] as $i => $x) {
            $h->fromArray([$i + 1, $x['responsable'], $x['fecha'] ? Carbon::parse($x['fecha'])->format('d/m/Y') : '', $x['banco'], $x['empresa'],
                $x['quien'] ?: $x['operacion'], $x['monto']], null, $c(0).$fila, true);
            $fila++;
        }
        $h->setCellValue($c(0).$fila, 'TOTAL DEPÓSITOS');
        $h->mergeCells($c(0).$fila.':'.$c(5).$fila);
        $h->setCellValue($c(6).$fila, $this->d['depositos']->sum('monto'));
        $h->getStyle($c(0).$fila.':'.$c(6).$fila)->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL)]);
        $this->formatoNumeros($h, $inicio + 1, $fila, $col + 6, $col + 6, '#,##0.00;-#,##0.00;"-"');
        $h->getStyle($c(0).$inicio.':'.$c(6).$fila)->applyFromArray(['borders' => $this->bordes()]);

        return $fila + 1;
    }

    /** Columna derecha: detalle de ventas por producto y precio. */
    private function detalle(Worksheet $h, int $fila, int $col): void
    {
        $c = fn (int $k) => Coordinate::stringFromColumnIndex($col + $k);
        $h->setCellValue($c(0).$fila, 'DETALLE VENTAS');
        $h->getStyle($c(0).$fila)->getFont()->setBold(true);
        $fila++;
        $h->fromArray(['PRODUCTO', 'CANTIDAD', 'P.U.', 'TOTAL'], null, $c(0).$fila);
        $h->getStyle($c(0).$fila.':'.$c(3).$fila)->applyFromArray($this->estiloCabecera());
        $inicio = $fila++;
        foreach ($this->d['porProductoPrecio'] as $codigo => $p) {
            foreach ($p['filas'] as $x) {
                $h->fromArray([$codigo, (int) $x->cantidad, (float) $x->precio, (float) $x->total], null, $c(0).$fila, true);
                $fila++;
            }
            $h->fromArray(['TOTAL '.$codigo, $p['cantidad'], null, $p['total']], null, $c(0).$fila, true);
            $h->getStyle($c(0).$fila.':'.$c(3).$fila)->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno('EEF3FA')]);
            $fila++;
        }
        $h->fromArray(['TOTAL', $this->d['detallePrecios']->sum('cantidad'), null, $this->d['detallePrecios']->sum('total')], null, $c(0).$fila, true);
        $h->getStyle($c(0).$fila.':'.$c(3).$fila)->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL)]);
        $h->getStyle($c(3).$fila)->getFont()->getColor()->setRGB(self::ROJO);
        $this->formatoNumeros($h, $inicio + 1, $fila, $col + 1, $col + 1, '#,##0;-#,##0;"-"');
        $this->formatoNumeros($h, $inicio + 1, $fila, $col + 2, $col + 3, '#,##0.00;-#,##0.00;"-"');
        $h->getStyle($c(0).$inicio.':'.$c(3).$fila)->applyFromArray(['borders' => $this->bordes()]);
    }

    private function titulo(Worksheet $h, int $fila, string $texto): int
    {
        $h->setCellValue("A{$fila}", $texto);
        $h->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(10);

        return $fila + 1;
    }

    private function formatoNumeros(Worksheet $h, int $desde, int $hasta, int $colIni, int $colFin, string $formato): void
    {
        if ($colFin < $colIni || $hasta < $desde) {
            return;
        }
        $rango = Coordinate::stringFromColumnIndex($colIni).$desde.':'.Coordinate::stringFromColumnIndex($colFin).$hasta;
        $h->getStyle($rango)->getNumberFormat()->setFormatCode($formato);
        $h->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function estiloCabecera(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 9],
            'fill' => $this->relleno(self::AZUL),
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $this->bordes(),
        ];
    }

    private function relleno(string $color): array
    {
        return ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]];
    }

    private function bordes(): array
    {
        return ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']]];
    }

    private function pie(Worksheet $h, int $fila): void
    {
        $h->setCellValue("A{$fila}", 'Generado el '.now()->format('d/m/Y H:i').' por '.(auth()->user()?->name ?? 'sistema'));
        $h->getStyle("A{$fila}")->getFont()->setSize(8)->getColor()->setRGB('808080');
    }
}
