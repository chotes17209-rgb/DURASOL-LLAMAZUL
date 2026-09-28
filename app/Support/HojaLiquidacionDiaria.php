<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de liquidación diaria con el formato del Excel de la empresa (RESUMEN GNRAL): todo en una sola hoja,
 * ventas liquidadas y de ruta, por depositar y depósitos a la izquierda; detalle de ventas por precio a la derecha.
 */
class HojaLiquidacionDiaria extends HojaExcel
{
    public const IMPORTES = ['venta' => 'Venta total', 'cobranza' => 'Cobranza', 'credito' => 'Crédito', 'varios' => 'Varios', 'fise' => 'FISE', 'vouchers' => 'Vouchers', 'depositos' => 'Depósitos', 'por_depositar' => 'Por depositar'];

    public function __construct(Carbon $fecha, private readonly array $d)
    {
        parent::__construct($fecha);
    }

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

    protected function tituloDocumento(): string
    {
        return 'la hoja de liquidación diaria del '.$this->fecha->format('d/m/Y');
    }

    protected function vistaPdf(): array
    {
        return ['pdf.hoja-liquidacion', $this->d];
    }

    protected function hoja(Worksheet $h): void
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
        $finDep = $this->tabla($h, $fila, 3 + count($codigos), 'Depósitos realizados',
            ['N°' => 'entero', 'Responsable' => 'texto', 'Fecha' => 'texto', 'Banco' => 'texto', 'Empresa' => 'texto', 'Quién / operación' => 'texto', 'Importe' => 'decimal'],
            $this->d['depositos']->values()->map(fn ($x, $i) => [$i + 1, $x['responsable'], $x['fecha'] ? Carbon::parse($x['fecha'])->format('d/m/Y') : '',
                $x['banco'], $x['empresa'], $x['quien'] ?: $x['operacion'], $x['monto']]),
            ['TOTAL DEPÓSITOS', '', '', '', '', '', $this->d['depositos']->sum('monto')]);
        $this->detalle($h, 6, $colDetalle);

        $this->anchos($h, 1, [13, 16]);
        for ($c = 3; $c <= $nCols; $c++) {
            $h->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth($c <= 2 + count($codigos) ? 8 : 13);
        }
        $this->anchos($h, $nCols + 1, [3, 12, 9, 10, 13]);
        $this->pie($h, max($fin, $finDep) + 1);
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
        $this->formatoNumeros($h, $inicio + 2, $fila, 3, 2 + count($codigos), self::ENTERO);
        $this->formatoNumeros($h, $inicio + 2, $fila, 3 + count($codigos), 2 + count($codigos) + count(self::IMPORTES), self::DECIMAL);
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
        $this->formatoNumeros($h, $inicio, $fila - 1, 3, 3, self::DECIMAL);
        $h->getStyle("A{$inicio}:D".($fila - 1))->applyFromArray(['borders' => $this->bordes()]);

        return $fila + 1;
    }

    /** Columna derecha: detalle de ventas por producto y precio, con subtotal por producto. */
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
            $h->getStyle($c(0).$fila.':'.$c(3).$fila)->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::CELESTE)]);
            $fila++;
        }
        $h->fromArray(['TOTAL', $this->d['detallePrecios']->sum('cantidad'), null, $this->d['detallePrecios']->sum('total')], null, $c(0).$fila, true);
        $h->getStyle($c(0).$fila.':'.$c(3).$fila)->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL)]);
        $h->getStyle($c(3).$fila)->getFont()->getColor()->setRGB(self::ROJO);
        $this->formatoNumeros($h, $inicio + 1, $fila, $col + 1, $col + 1, self::ENTERO);
        $this->formatoNumeros($h, $inicio + 1, $fila, $col + 2, $col + 3, self::DECIMAL);
        $h->getStyle($c(0).$inicio.':'.$c(3).$fila)->applyFromArray(['borders' => $this->bordes()]);
    }
}
