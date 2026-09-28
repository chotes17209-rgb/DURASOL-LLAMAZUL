<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reporte descargable en PDF (con los logos) o en Excel (.xlsx) a partir de las mismas tablas.
 *
 * Uso:
 *   (new Reporte('Parte diario', 'Lunes 14/09/2026'))
 *       ->tabla('Ingreso de llenos', ['Placa' => 'texto', 'S-10' => 'entero'], $filas, $totales)
 *       ->descargar('pdf', 'parte-2026-09-14');
 *
 * Tipos de columna: texto, entero, decimal (2 decimales), soles.
 */
class Reporte
{
    /** @var array<int, array{titulo: ?string, columnas: array<string, string>, filas: array, total: ?array, nota: ?string}> */
    private array $tablas = [];

    /** @var array<int, array{0: string, 1: string}> */
    private array $datos = [];

    public function __construct(
        public readonly string $titulo,
        public readonly ?string $subtitulo = null,
        public readonly bool $horizontal = false,
    ) {}

    /** Pares etiqueta => valor que se muestran en la cabecera (fecha, chofer, responsable...). */
    public function datos(array $pares): static
    {
        foreach ($pares as $k => $v) {
            $this->datos[] = [$k, (string) $v];
        }

        return $this;
    }

    public function tabla(?string $titulo, array $columnas, iterable $filas, ?array $total = null, ?string $nota = null): static
    {
        $lista = [];
        foreach ($filas as $fila) {
            $lista[] = array_values((array) $fila);
        }
        $this->tablas[] = ['titulo' => $titulo, 'columnas' => $columnas, 'filas' => $lista, 'total' => $total !== null ? array_values($total) : null, 'nota' => $nota];

        return $this;
    }

    public function descargar(string $formato, string $archivo): Response
    {
        AuditLogger::event('exportacion', 'Descargó el reporte "'.$this->titulo.'" en '.strtoupper($formato), null, ['archivo' => $archivo]);

        return $formato === 'xlsx' ? $this->excel($archivo) : $this->pdf($archivo);
    }

    public static function formatear(mixed $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($tipo) {
            'entero' => (int) $valor === 0 ? '' : number_format((float) $valor, 0, '.', ','),
            'decimal' => number_format((float) $valor, 2, '.', ','),
            'soles' => 'S/ '.number_format((float) $valor, 2, '.', ','),
            default => (string) $valor,
        };
    }

    private function pdf(string $archivo): Response
    {
        $logo = fn (string $f) => 'data:image/jpeg;base64,'.base64_encode((string) @file_get_contents(public_path('img/'.$f)));

        return Pdf::loadView('pdf.reporte', [
            'r' => $this,
            'tablas' => $this->tablas,
            'datos' => $this->datos,
            'logos' => [$logo('durasol.jpg'), $logo('llamazul.jpg')],
        ])->setOption('isPhpEnabled', true)->setPaper('a4', $this->horizontal ? 'landscape' : 'portrait')->download($archivo.'.pdf');
    }

    private function excel(string $archivo): Response
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator(config('app.name'))->setTitle($this->titulo);
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $this->titulo), 0, 31));
        $hoja->getDefaultRowDimension()->setRowHeight(16);
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $anchoMax = max(array_map(fn ($t) => count($t['columnas']), $this->tablas ?: [['columnas' => [1]]]));

        foreach (['durasol.jpg' => 'A1', 'llamazul.jpg' => 'C1'] as $img => $celda) {
            if (is_file(public_path('img/'.$img))) {
                $d = new Drawing;
                $d->setPath(public_path('img/'.$img))->setHeight(34)->setCoordinates($celda)->setOffsetY(2)->setWorksheet($hoja);
            }
        }
        $hoja->getRowDimension(1)->setRowHeight(30);
        $hoja->getRowDimension(2)->setRowHeight(8);
        $fila = 3;
        $hoja->setCellValue("A{$fila}", mb_strtoupper($this->titulo));
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1A3A80');
        $fila++;
        if ($this->subtitulo) {
            $hoja->setCellValue("A{$fila}", $this->subtitulo);
            $hoja->getStyle("A{$fila}")->getFont()->getColor()->setRGB('475569');
            $fila++;
        }
        foreach ($this->datos as [$k, $v]) {
            $hoja->setCellValue("A{$fila}", $k);
            $hoja->setCellValue("B{$fila}", $v);
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
            $fila++;
        }
        $fila++;

        foreach ($this->tablas as $t) {
            $cols = array_values($t['columnas']);
            $n = count($cols);
            $fin = Coordinate::stringFromColumnIndex($n);
            if ($t['titulo']) {
                $hoja->setCellValue("A{$fila}", mb_strtoupper($t['titulo']));
                $hoja->getStyle("A{$fila}")->getFont()->setBold(true)->getColor()->setRGB('1A3A80');
                $fila++;
            }
            $inicioTabla = $fila;
            $hoja->fromArray(array_keys($t['columnas']), null, "A{$fila}");
            $hoja->getStyle("A{$fila}:{$fin}{$fila}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A80']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $fila++;
            $filas = $t['filas'];
            if ($t['total'] !== null) {
                $filas[] = $t['total'];
            }
            foreach ($filas as $i => $valores) {
                foreach ($valores as $c => $valor) {
                    $celda = Coordinate::stringFromColumnIndex($c + 1).$fila;
                    $tipo = $cols[$c] ?? 'texto';
                    if ($tipo !== 'texto' && is_numeric($valor)) {
                        $hoja->setCellValueExplicit($celda, (float) $valor, DataType::TYPE_NUMERIC);
                        $hoja->getStyle($celda)->getNumberFormat()->setFormatCode(match ($tipo) {
                            'entero' => '#,##0;-#,##0;""',
                            'soles' => '"S/ "#,##0.00',
                            default => '#,##0.00',
                        });
                    } else {
                        $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
                    }
                }
                if ($t['total'] !== null && $i === count($filas) - 1) {
                    $hoja->getStyle("A{$fila}:{$fin}{$fila}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EDF5']],
                    ]);
                }
                $fila++;
            }
            $hoja->getStyle("A{$inicioTabla}:{$fin}".($fila - 1))->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C7CED8');
            if ($t['nota']) {
                $hoja->setCellValue("A{$fila}", $t['nota']);
                $hoja->getStyle("A{$fila}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
                $fila++;
            }
            $fila++;
        }

        for ($c = 1; $c <= max($anchoMax, 4); $c++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        $hoja->setCellValue("A{$fila}", 'Generado el '.now()->format('d/m/Y H:i').' por '.(auth()->user()?->name ?? 'sistema'));
        $hoja->getStyle("A{$fila}")->getFont()->setSize(8)->getColor()->setRGB('94A3B8');
        $hoja->getPageSetup()->setOrientation($this->horizontal ? 'landscape' : 'portrait')->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $archivo.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
