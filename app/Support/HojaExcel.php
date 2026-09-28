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
 * Base de los reportes de liquidación con el formato de las hojas Excel de la empresa:
 * todo en una sola hoja (una página horizontal en PDF y una hoja en Excel), título en recuadro,
 * fecha en amarillo, cabeceras celestes con bordes y totales resaltados.
 */
abstract class HojaExcel
{
    protected const AZUL = 'BDD7EE';

    protected const CELESTE = 'EEF3FA';

    protected const ROJO = 'C00000';

    protected const DECIMAL = '#,##0.00;-#,##0.00;"-"';

    protected const ENTERO = '#,##0;-#,##0;"-"';

    public function __construct(protected readonly Carbon $fecha) {}

    /** Llena la hoja de Excel. */
    abstract protected function hoja(Worksheet $h): void;

    /** [vista, datos] del PDF. */
    abstract protected function vistaPdf(): array;

    abstract protected function tituloDocumento(): string;

    public function descargar(string $formato, string $archivo): Response
    {
        AuditLogger::event('exportacion', 'Descargó '.$this->tituloDocumento().' en '.strtoupper($formato), null, ['archivo' => $archivo]);

        return $formato === 'xlsx' ? $this->excel($archivo) : $this->pdf($archivo);
    }

    private function pdf(string $archivo): Response
    {
        $logo = fn (string $f) => 'data:image/jpeg;base64,'.base64_encode((string) @file_get_contents(public_path('img/'.$f)));
        [$vista, $datos] = $this->vistaPdf();

        return Pdf::loadView($vista, $datos + ['fecha' => $this->fecha, 'logos' => [$logo('durasol.jpg'), $logo('llamazul.jpg')]])
            ->setOption('isPhpEnabled', true)->setPaper('a4', 'landscape')->download($archivo.'.pdf');
    }

    private function excel(string $archivo): Response
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator(config('app.name'))->setTitle(ucfirst($this->tituloDocumento()));
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $this->hoja($libro->getActiveSheet());

        return response()->streamDownload(fn () => (new Xlsx($libro))->save('php://output'), $archivo.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Logos, título en recuadro, fecha en amarillo y una línea de datos; devuelve la fila siguiente. */
    protected function cabecera(Worksheet $h, string $titulo, int $ultimaColumna, string $datos = ''): int
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
            'font' => ['bold' => true, 'size' => 14],
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
        if ($datos !== '') {
            $h->setCellValue('E4', $datos);
            $h->getStyle('E4')->getFont()->setBold(true);
        }
        $h->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(1);
        $h->getPageMargins()->setLeft(0.3)->setRight(0.3)->setTop(0.4)->setBottom(0.4);

        return 6;
    }

    /**
     * Tabla con título, cabecera, filas y total opcional a partir de la columna $col.
     * $columnas = [nombre => 'texto'|'entero'|'decimal']; $rojas = índices de columnas en rojo.
     * Devuelve la fila siguiente, dejando una de separación.
     */
    protected function tabla(Worksheet $h, int $fila, int $col, string $titulo, array $columnas, iterable $filas, ?array $total = null, array $rojas = []): int
    {
        $c = fn (int $k) => Coordinate::stringFromColumnIndex($col + $k);
        $ultima = count($columnas) - 1;
        $h->setCellValue($c(0).$fila, mb_strtoupper($titulo));
        $h->getStyle($c(0).$fila)->getFont()->setBold(true);
        $fila++;
        $h->fromArray(array_map('mb_strtoupper', array_keys($columnas)), null, $c(0).$fila);
        $h->getStyle($c(0).$fila.':'.$c($ultima).$fila)->applyFromArray($this->estiloCabecera());
        $inicio = $fila++;
        $hay = false;
        foreach ($filas as $valores) {
            $h->fromArray(array_values($valores), null, $c(0).$fila, true);
            $fila++;
            $hay = true;
        }
        if (! $hay) {
            $h->setCellValue($c(0).$fila, 'Sin registros');
            $h->mergeCells($c(0).$fila.':'.$c($ultima).$fila);
            $h->getStyle($c(0).$fila)->getFont()->setItalic(true)->getColor()->setRGB('808080');
            $fila++;
        }
        if ($total !== null) {
            $h->fromArray($total, null, $c(0).$fila, true);
            $h->getStyle($c(0).$fila.':'.$c($ultima).$fila)->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL)]);
            $fila++;
        }
        foreach (array_values($columnas) as $k => $tipo) {
            if ($tipo !== 'texto') {
                $this->formatoNumeros($h, $inicio + 1, $fila - 1, $col + $k, $col + $k, $tipo === 'entero' ? self::ENTERO : self::DECIMAL);
            }
        }
        foreach ($rojas as $k) {
            $h->getStyle($c($k).($inicio + 1).':'.$c($k).($fila - 1))->getFont()->setBold(true)->getColor()->setRGB(self::ROJO);
        }
        $h->getStyle($c(0).$inicio.':'.$c($ultima).($fila - 1))->applyFromArray(['borders' => $this->bordes()]);

        return $fila + 1;
    }

    protected function titulo(Worksheet $h, int $fila, string $texto): int
    {
        $h->setCellValue("A{$fila}", $texto);
        $h->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(10);

        return $fila + 1;
    }

    protected function formatoNumeros(Worksheet $h, int $desde, int $hasta, int $colIni, int $colFin, string $formato): void
    {
        if ($colFin < $colIni || $hasta < $desde) {
            return;
        }
        $rango = Coordinate::stringFromColumnIndex($colIni).$desde.':'.Coordinate::stringFromColumnIndex($colFin).$hasta;
        $h->getStyle($rango)->getNumberFormat()->setFormatCode($formato);
        $h->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    protected function estiloCabecera(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 9],
            'fill' => $this->relleno(self::AZUL),
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $this->bordes(),
        ];
    }

    protected function relleno(string $color): array
    {
        return ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]];
    }

    protected function bordes(): array
    {
        return ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']]];
    }

    /** Anchos de columnas consecutivas desde $desde. */
    protected function anchos(Worksheet $h, int $desde, array $anchos): void
    {
        foreach (array_values($anchos) as $k => $ancho) {
            $h->getColumnDimension(Coordinate::stringFromColumnIndex($desde + $k))->setWidth($ancho);
        }
    }

    protected function pie(Worksheet $h, int $fila): void
    {
        $h->setCellValue("A{$fila}", 'Generado el '.now()->format('d/m/Y H:i').' por '.(auth()->user()?->name ?? 'sistema'));
        $h->getStyle("A{$fila}")->getFont()->setSize(8)->getColor()->setRGB('808080');
    }
}
