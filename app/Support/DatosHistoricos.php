<?php

namespace App\Support;

use App\Enums\EstadoLiquidacion;
use App\Enums\TipoChofer;
use App\Models\Chofer;
use App\Models\Liquidacion;
use App\Services\CostoService;
use App\Services\LiquidacionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos históricos que vienen de los archivos del cliente (compras de planta, cuotas,
 * FISE del 25/09). Se usan al cargar una base nueva y para completar una base ya cargada.
 */
class DatosHistoricos
{
    public static function archivo(): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/compras_setiembre.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Compras de agosto (total mensual del registro de compras) y de setiembre (diario), y cuotas. */
    public static function cargarCompras(): void
    {
        $datos = self::archivo();
        $empresas = DB::table('empresas')->pluck('id', 'nombre');
        $productos = DB::table('productos')->pluck('id', 'codigo');
        $costos = app(CostoService::class);
        $filas = [];
        $agregar = function (string $fecha, string $empresa, array $cantidades, string $observacion) use (&$filas, $empresas, $productos, $costos) {
            foreach ($cantidades as $codigo => $cantidad) {
                if ($cantidad > 0 && isset($empresas[$empresa], $productos[$codigo])) {
                    $filas[] = [
                        'fecha' => $fecha, 'empresa_id' => $empresas[$empresa], 'producto_id' => $productos[$codigo], 'cantidad' => (int) $cantidad,
                        'precio_unitario' => $costos->costoUnitario($empresas[$empresa], $productos[$codigo], $fecha),
                        'origen' => 'excel', 'observacion' => $observacion, 'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        };
        foreach ($datos['totales_mes_anterior'] as $mes => $porEmpresa) {
            foreach ($porEmpresa as $empresa => $cantidades) {
                $agregar(Carbon::parse($mes.'-01')->endOfMonth()->toDateString(), $empresa, $cantidades, 'Total del mes según el registro de compras (sin detalle diario).');
            }
        }
        foreach ($datos['compras'] as $empresa => $dias) {
            foreach ($dias as $fecha => $cantidades) {
                $agregar($fecha, $empresa, $cantidades, 'Registro de compras de setiembre.');
            }
        }
        DB::table('compras_planta')->insert($filas);

        foreach ($datos['cuotas'] as $mes => $porEmpresa) {
            foreach ($porEmpresa as $empresa => $cantidades) {
                foreach ($cantidades as $codigo => $cantidad) {
                    if (isset($empresas[$empresa], $productos[$codigo])) {
                        DB::table('cuotas_compra')->updateOrInsert(
                            ['mes' => $mes, 'empresa_id' => $empresas[$empresa], 'producto_id' => $productos[$codigo]],
                            ['cantidad' => $cantidad, 'created_at' => now(), 'updated_at' => now()],
                        );
                    }
                }
            }
        }
    }

    /**
     * FISE del 25/09 (hoja RESUMEN GNRAL: solo importe por chofer). Se expresa en vales de S/ 20
     * y los mínimos de S/ 43 necesarios para llegar exactamente al importe.
     */
    public static function cargarFise25Setiembre(): void
    {
        $service = app(LiquidacionService::class);
        foreach (self::archivo()['fise_25_setiembre'] as $alias => $importe) {
            $chofer = Chofer::withTrashed()->where('alias', $alias)->first();
            if (! $chofer) {
                continue;
            }
            $liquidacion = Liquidacion::where('chofer_id', $chofer->id)->where('fecha_venta', '2026-09-25')->where('historico', true)->first()
                ?? tap(Liquidacion::create([
                    'codigo' => 'TMP-'.Str::random(10), 'fecha_venta' => '2026-09-25', 'fecha_liquidacion' => '2026-09-26',
                    'chofer_id' => $chofer->id, 'vehiculo_id' => $chofer->vehiculo_id,
                    'tipo' => in_array($chofer->tipo, [TipoChofer::Ruta, TipoChofer::Almacen], true) ? $chofer->tipo : TipoChofer::Local,
                    'estado' => EstadoLiquidacion::Cerrada, 'historico' => true, 'cerrada_at' => '2026-09-26 18:00:00',
                    'observaciones' => 'Importada del Excel (RESUMEN GNRAL del 25/09): solo FISE.',
                ]), fn ($l) => $l->updateQuietly(['codigo' => $service->codigoPara($l->id)]));
            if ($liquidacion->fises()->exists()) {
                continue;
            }
            [$de20, $de43] = self::vales($importe);
            foreach ([20 => $de20, 43 => $de43] as $valor => $cantidad) {
                if ($cantidad > 0) {
                    $liquidacion->fises()->create(['valor' => $valor, 'cantidad' => $cantidad, 'subtotal' => $valor * $cantidad]);
                }
            }
            $service->recalcular($liquidacion);
        }
    }

    /** @return array{0: int, 1: int} vales de 20 y de 43 que suman exactamente el importe */
    public static function vales(int $importe): array
    {
        for ($de43 = 0; $de43 * 43 <= $importe; $de43++) {
            if (($importe - $de43 * 43) % 20 === 0) {
                return [intdiv($importe - $de43 * 43, 20), $de43];
            }
        }

        return [intdiv($importe, 20), 0];
    }

    /**
     * Ventas del Excel con el mes mal digitado (fecha de venta un mes después de su liquidación):
     * se pasan a la liquidación correcta del mismo chofer.
     */
    public static function corregirFechasVentas(): void
    {
        $service = app(LiquidacionService::class);
        $casos = [
            ['2026-09-18', '2026-08-18', 'FREDY', 20, 1400.0],
            ['2026-09-28', '2026-08-28', 'FREDY', 60, 2616.0],
            ['2026-09-28', '2026-08-28', 'FREDY', 290, 12644.0],
            ['2026-09-28', '2026-08-28', 'AGUILAR', 350, 15330.0],
            ['2026-08-17', '2026-07-17', 'RUFINO', 586, 0.0],
        ];
        foreach ($casos as [$de, $a, $alias, $cantidad, $total]) {
            $chofer = Chofer::withTrashed()->where('alias', $alias)->first();
            $origen = $chofer ? Liquidacion::where('chofer_id', $chofer->id)->where('fecha_venta', $de)->where('historico', true)->first() : null;
            $item = $origen?->items()->where('cantidad', $cantidad)->whereBetween('total', [$total - 0.01, $total + 0.01])->first();
            if (! $item) {
                continue;
            }
            $destino = Liquidacion::where('chofer_id', $chofer->id)->where('fecha_venta', $a)->where('historico', true)->first()
                ?? tap($origen->replicate(['codigo']), function ($l) use ($a, $service) {
                    $l->fill(['codigo' => 'TMP-'.Str::random(10), 'fecha_venta' => $a, 'fecha_liquidacion' => max($a, $l->fecha_liquidacion->toDateString())])->save();
                    $l->updateQuietly(['codigo' => $service->codigoPara($l->id)]);
                });
            $item->update(['liquidacion_id' => $destino->id]);
            DB::table('cuentas_por_cobrar')->where('liquidacion_item_id', $item->id)->update(['liquidacion_id' => $destino->id, 'fecha' => $a]);
            $service->recalcular($destino);
            if ($origen->items()->doesntExist() && $origen->cobranzas()->doesntExist() && $origen->fises()->doesntExist()) {
                $origen->forceDelete();
            } else {
                $service->recalcular($origen);
            }
        }
    }
}
