<?php

namespace App\Support;

use App\Enums\TipoChofer;
use App\Models\Liquidacion;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Liquidación individual con el formato de la hoja REGISTRO del Excel, en una sola hoja:
 * registro de ventas con cobranzas, FISE, varios y depósitos a la izquierda; resumen y detalle por producto a la derecha.
 */
class HojaLiquidacion extends HojaExcel
{
    public function __construct(private readonly Liquidacion $l)
    {
        $l->loadMissing(['items.cliente', 'items.producto', 'items.empresa', 'fises.cliente', 'gastos', 'depositos', 'cobranzas.cliente', 'chofer', 'vehiculo']);
        parent::__construct($l->fecha_liquidacion ?? $l->fecha_venta);
    }

    /** Cabecera de datos de la liquidación. */
    public function datos(): array
    {
        $l = $this->l;

        return [
            'Liquidación' => $l->codigo,
            'Responsable' => $l->chofer?->alias,
            'Placa' => $l->vehiculo?->placa ?? 'LOCAL',
            $l->tipo === TipoChofer::Ruta ? 'Salida' : 'Venta' => $l->fecha_venta?->format('d/m/Y'),
            'Liquidada' => $l->fecha_liquidacion?->format('d/m/Y'),
            'Estado' => $l->estado->label(),
        ];
    }

    /** Resumen de la hoja: [concepto, importe, resaltado]. */
    public function resumen(): array
    {
        $l = $this->l;
        $aEntregar = round((float) $l->efectivo_esperado - (float) $l->total_depositos, 2);
        $filas = [
            ['Venta total', $l->total_venta, true],
            ['(+) Cobranza', $l->total_cobranzas, false],
            ['(−) Crédito', $l->total_credito, false],
            ['(−) Varios', $l->total_gastos, false],
            ['(−) FISE', $l->total_fises, false],
            ['(−) Vouchers', $l->total_vouchers, false],
            ['Por depositar', $l->efectivo_esperado, true],
            ['(−) Depósitos', $l->total_depositos, false],
            ['Efectivo a entregar', $aEntregar, true],
        ];
        if ($l->efectivo_entregado !== null) {
            $filas[] = ['Entregado', $l->efectivo_entregado, false];
            $filas[] = ['Diferencia', $l->diferencia, true];
        }

        return array_map(fn ($f) => [$f[0], (float) $f[1], $f[2]], $filas);
    }

    /** Balones por presentación: [código => [cantidad, total, vacíos]]. */
    public function porProducto(): array
    {
        return $this->l->items->groupBy(fn ($i) => $i->producto?->codigo)->map(fn ($g) => [
            'cantidad' => (int) $g->sum('cantidad'), 'total' => round((float) $g->sum('total'), 2), 'vacios' => (int) $g->sum('vacios_devueltos'),
        ])->all();
    }

    protected function tituloDocumento(): string
    {
        return 'la liquidación '.$this->l->codigo;
    }

    protected function vistaPdf(): array
    {
        return ['pdf.liquidacion', ['l' => $this->l, 'datos' => $this->datos(), 'resumen' => $this->resumen(), 'porProducto' => $this->porProducto()]];
    }

    protected function hoja(Worksheet $h): void
    {
        $l = $this->l;
        $h->setTitle('Liquidación '.$l->codigo);
        $datos = implode('    ·    ', array_map(fn ($k, $v) => mb_strtoupper($k).': '.$v, array_keys($this->datos()), $this->datos()));
        $fila = $this->cabecera($h, 'HOJA DE LIQUIDACIÓN', 13, $datos);

        $contado = fn ($i) => (float) $i->total - (float) $i->monto_credito;
        $finIzq = $this->tabla($h, $fila, 1, 'Registro de ventas', [
            'N°' => 'entero', 'Código' => 'texto', 'Cliente' => 'texto', 'Empresa' => 'texto', 'Pres.' => 'texto', 'Cant.' => 'entero', 'P.U.' => 'decimal',
            'Total' => 'decimal', 'Vacíos dev.' => 'entero', 'Crédito' => 'decimal', 'Contado' => 'decimal', 'Pago' => 'texto', 'N° op.' => 'texto',
        ], $l->items->values()->map(fn ($i, $k) => [$k + 1, $i->cliente?->codigo, $i->cliente?->nombreMostrar(), $i->empresa?->nombre, $i->producto?->codigo,
            $i->cantidad, (float) $i->precio, (float) $i->total, $i->vacios_devueltos, (float) $i->monto_credito, $contado($i), $i->metodo_pago->label(), $i->numero_operacion]),
            ['TOTAL', '', '', '', '', $l->items->sum('cantidad'), null, (float) $l->total_venta, $l->items->sum('vacios_devueltos'), (float) $l->total_credito,
                (float) $l->total_venta - (float) $l->total_credito, '', ''], [7]);

        // Cobranzas y FISE (columna C); varios y depósitos (columna H), uno al lado del otro.
        $finC = $this->tabla($h, $finIzq, 3, 'Cobranzas', ['Cliente' => 'texto', 'Pago' => 'texto', 'Monto' => 'decimal'],
            $l->cobranzas->map(fn ($c) => [$c->cliente?->nombreMostrar(), $c->metodo_pago->label(), (float) $c->monto]), ['TOTAL', '', (float) $l->total_cobranzas]);
        $finC = $this->tabla($h, $finC, 3, 'Vales FISE', ['Cliente' => 'texto', 'Valor' => 'decimal', 'Cant.' => 'entero', 'Importe' => 'decimal'],
            $l->fises->map(fn ($f) => [$f->cliente?->nombreMostrar() ?? 'General', (float) $f->valor, $f->cantidad, (float) $f->subtotal]),
            ['TOTAL', null, $l->fises->sum('cantidad'), (float) $l->total_fises]);
        $finH = $this->tabla($h, $finIzq, 8, 'Varios', ['Concepto' => 'texto', 'Comprobante' => 'texto', 'Monto' => 'decimal'],
            $l->gastos->map(fn ($g) => [$g->concepto, $g->comprobante, (float) $g->monto]), ['TOTAL', '', (float) $l->total_gastos]);
        $finH = $this->tabla($h, $finH, 8, 'Depósitos', ['Cuenta / destino' => 'texto', 'N° operación' => 'texto', 'Monto' => 'decimal'],
            $l->depositos->map(fn ($d) => [$d->destino, $d->numero_operacion, (float) $d->monto]), ['TOTAL', '', (float) $l->total_depositos]);

        // Columna derecha: resumen y balones por presentación.
        $resumen = $this->resumen();
        $finR = $this->tabla($h, $fila, 15, 'Resumen', ['Concepto' => 'texto', 'Importe' => 'decimal'], array_map(fn ($r) => [$r[0], $r[1]], $resumen));
        foreach ($resumen as $k => $r) {
            if ($r[2]) {
                $h->getStyle('O'.($fila + 2 + $k).':P'.($fila + 2 + $k))->applyFromArray(['font' => ['bold' => true], 'fill' => $this->relleno(self::AZUL)]);
            }
        }
        $h->getStyle('P'.($fila + 2 + 8))->getFont()->setSize(12)->getColor()->setRGB(self::ROJO);
        $pp = $this->porProducto();
        $finR = $this->tabla($h, $finR, 15, 'Detalle por producto', ['Producto' => 'texto', 'Cant.' => 'entero', 'Total' => 'decimal', 'Vacíos' => 'entero'],
            array_map(fn ($c, $p) => [$c, $p['cantidad'], $p['total'], $p['vacios']], array_keys($pp), $pp),
            ['TOTAL', array_sum(array_column($pp, 'cantidad')), array_sum(array_column($pp, 'total')), array_sum(array_column($pp, 'vacios'))]);

        $this->anchos($h, 1, [5, 9, 30, 11, 7, 8, 9, 12, 9, 11, 11, 11, 12, 3, 22, 12, 12, 9]);
        $this->pie($h, max($finC, $finH, $finR));
    }
}
