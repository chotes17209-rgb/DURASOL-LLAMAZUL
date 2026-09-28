<?php

namespace App\Services;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoGuia;
use App\Enums\EstadoStock;
use App\Models\Canje;
use App\Models\Despacho;
use App\Models\Guia;
use App\Models\MovimientoStockManual;
use App\Models\Producto;

/**
 * Traduce cada documento de logística en sus movimientos de kardex.
 */
class LogisticaService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Guía de planta:
     *  - al salir: bajan los vacíos plomo, los de color y los cambios que se llevan;
     *  - al recibir: suben los llenos (comprados + reposición de cambios) y vuelven los vacíos rechazados.
     */
    public function aplicarGuia(Guia $guia): void
    {
        $guia->loadMissing('detalles.producto');

        if ($guia->historico || $guia->estado === EstadoGuia::Anulada) {
            $this->stock->revertir($guia);

            return;
        }

        $lineas = [];
        $ref = "Guía {$guia->numero_guia}";
        foreach ($guia->detalles as $d) {
            $envase = $this->stock->envaseDe($d->producto);
            $salida = ['fecha' => $guia->fecha_salida, 'empresa_id' => $guia->empresa_id];
            $lineas[] = $salida + ['producto_id' => $envase, 'estado' => EstadoStock::Vacio, 'cantidad' => -$d->vacios_enviados, 'concepto' => "$ref · vacíos a planta"];
            $lineas[] = $salida + ['producto_id' => $envase, 'estado' => EstadoStock::Color, 'cantidad' => -$d->colores_enviados, 'concepto' => "$ref · colores a planta"];
            $lineas[] = $salida + ['producto_id' => $d->producto_id, 'estado' => EstadoStock::Cambio, 'cantidad' => -$d->cambios_enviados, 'concepto' => "$ref · cambios a planta"];

            if ($guia->estado === EstadoGuia::Recibida) {
                $entrada = ['fecha' => $guia->fecha_recepcion ?? $guia->fecha_salida, 'empresa_id' => $guia->empresa_id];
                $lineas[] = $entrada + ['producto_id' => $d->producto_id, 'estado' => EstadoStock::Lleno, 'cantidad' => $d->llenos_recibidos, 'concepto' => "$ref · llenos comprados"];
                $lineas[] = $entrada + ['producto_id' => $d->producto_id, 'estado' => EstadoStock::Lleno, 'cantidad' => $d->cambios_repuestos, 'concepto' => "$ref · reposición de cambios"];
                $lineas[] = $entrada + ['producto_id' => $envase, 'estado' => EstadoStock::Vacio, 'cantidad' => $d->vacios_rechazados, 'concepto' => "$ref · vacíos rechazados"];
                $lineas[] = $entrada + ['producto_id' => $envase, 'estado' => EstadoStock::Color, 'cantidad' => $d->colores_rechazados, 'concepto' => "$ref · colores rechazados"];
            }
        }

        $this->stock->sincronizar($guia, $lineas);
    }

    /**
     * Despacho a chofer:
     *  - al salir: bajan los llenos (o los envases vacíos si se venden envases);
     *  - al retornar: vuelven los llenos no vendidos, los vacíos, los de color y los cambios.
     *    Cada cambio recibido consumió un lleno entregado al cliente.
     */
    public function aplicarDespacho(Despacho $despacho): void
    {
        $despacho->loadMissing('detalles.producto', 'chofer');

        if ($despacho->historico || $despacho->estado === EstadoDespacho::Anulado) {
            $this->stock->revertir($despacho);

            return;
        }

        $lineas = [];
        $ref = 'Despacho '.$despacho->chofer->alias.' v'.$despacho->vuelta;
        foreach ($despacho->detalles as $d) {
            if (! $d->producto->controla_stock) {
                continue;
            }
            $envase = $this->stock->envaseDe($d->producto);
            $esEnvase = $d->producto->tipo === Producto::TIPO_ENVASE;
            $productoSalida = $esEnvase ? $envase : $d->producto_id;
            $estadoSalida = $esEnvase ? EstadoStock::Vacio : EstadoStock::Lleno;

            $salida = ['fecha' => $despacho->fecha, 'empresa_id' => $d->empresa_id];
            $lineas[] = $salida + ['producto_id' => $productoSalida, 'estado' => $estadoSalida, 'cantidad' => -$d->llenos_salida, 'concepto' => "$ref · salida"];

            if ($despacho->estado === EstadoDespacho::Retornado) {
                $lineas[] = $salida + ['producto_id' => $productoSalida, 'estado' => $estadoSalida, 'cantidad' => $d->llenos_retorno, 'concepto' => "$ref · retorno no vendidos"];
                $lineas[] = $salida + ['producto_id' => $envase, 'estado' => EstadoStock::Vacio, 'cantidad' => $d->vacios_retorno, 'concepto' => "$ref · vacíos recogidos"];
                $lineas[] = $salida + ['producto_id' => $envase, 'estado' => EstadoStock::Color, 'cantidad' => $d->colores_retorno, 'concepto' => "$ref · colores recogidos"];
                $lineas[] = $salida + ['producto_id' => $d->producto_id, 'estado' => EstadoStock::Cambio, 'cantidad' => $d->cambios_retorno, 'concepto' => "$ref · cambios recogidos"];
            }
        }

        $this->stock->sincronizar($despacho, $lineas);
    }

    /** Canje: salen vacíos de color, entran vacíos plomo. */
    public function aplicarCanje(Canje $canje): void
    {
        $base = ['fecha' => $canje->fecha, 'empresa_id' => null, 'producto_id' => $canje->producto_id];
        $this->stock->sincronizar($canje, [
            $base + ['estado' => EstadoStock::Color, 'cantidad' => -$canje->colores_entregados, 'concepto' => "Canje con {$canje->contraparte} · colores entregados"],
            $base + ['estado' => EstadoStock::Vacio, 'cantidad' => $canje->plomos_recibidos, 'concepto' => "Canje con {$canje->contraparte} · plomos recibidos"],
        ]);
    }

    public function aplicarManual(MovimientoStockManual $mov): void
    {
        $concepto = $mov->tipo->label().($mov->referencia ? " · {$mov->referencia}" : '');
        $this->stock->sincronizar($mov, [[
            'fecha' => $mov->fecha,
            'empresa_id' => $mov->empresa_id,
            'producto_id' => $mov->producto_id,
            'estado' => $mov->estado,
            'cantidad' => $mov->cantidadConSigno(),
            'concepto' => $concepto,
        ]]);
    }
}
