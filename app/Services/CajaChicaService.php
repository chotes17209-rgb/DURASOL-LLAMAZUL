<?php

namespace App\Services;

use App\Enums\CategoriaCaja;
use App\Models\Arqueo;
use App\Models\CajaChicaMovimiento;
use App\Models\CajaMovimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Caja chica: fondo para gastos menores que se repone desde la caja general.
 * Cada reposición es un egreso automático de la caja general (categoría «Caja chica»),
 * de modo que ambas cajas cuadran entre sí.
 */
class CajaChicaService
{
    public function __construct(private readonly CajaService $caja) {}

    public function saldoAl(Carbon $fecha): float
    {
        $filas = CajaChicaMovimiento::where('fecha', '<=', $fecha->toDateString())
            ->selectRaw('tipo, SUM(monto) as total')->groupBy('tipo')->pluck('total', 'tipo');

        return round((float) ($filas[CajaChicaMovimiento::APERTURA] ?? 0) + (float) ($filas[CajaChicaMovimiento::REPOSICION] ?? 0)
            - (float) ($filas[CajaChicaMovimiento::GASTO] ?? 0), 2);
    }

    /**
     * Movimientos de un rango con el saldo acumulado fila por fila (como el reporte impreso).
     *
     * El saldo inicial es el saldo final del día anterior; el día de apertura, el monto de la apertura.
     *
     * @return array{saldo_inicial: float, apertura: ?CajaChicaMovimiento, reposiciones: float, gastos: float, saldo_final: float, filas: Collection, por_concepto: Collection}
     */
    public function resumen(Carbon $desde, Carbon $hasta, ?string $concepto = null): array
    {
        $todos = CajaChicaMovimiento::with(['vehiculo', 'chofer', 'user'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')->orderByRaw("CASE WHEN tipo = 'reposicion' THEN 0 ELSE 1 END")->orderBy('id')
            ->get();
        $apertura = $todos->firstWhere('tipo', CajaChicaMovimiento::APERTURA);
        $movimientos = $todos->where('tipo', '!=', CajaChicaMovimiento::APERTURA)->values();

        $saldo = $saldoInicial = round($this->saldoAl($desde->copy()->subDay()) + (float) ($apertura?->monto ?? 0), 2);

        $filas = $movimientos->map(function (CajaChicaMovimiento $m) use (&$saldo) {
            $saldo = round($saldo + $m->montoConSigno(), 2);

            return ['movimiento' => $m, 'saldo' => $saldo];
        });

        $gastos = $movimientos->where('tipo', CajaChicaMovimiento::GASTO);

        return [
            'saldo_inicial' => $saldoInicial,
            'apertura' => $apertura,
            'reposiciones' => round((float) $movimientos->where('tipo', CajaChicaMovimiento::REPOSICION)->sum('monto'), 2),
            'gastos' => round((float) $gastos->sum('monto'), 2),
            'saldo_final' => $saldo,
            'filas' => $concepto ? $filas->filter(fn ($f) => $f['movimiento']->concepto === $concepto)->values() : $filas,
            'por_concepto' => $gastos->groupBy('concepto')->map(fn ($g) => ['cantidad' => $g->count(), 'total' => round((float) $g->sum('monto'), 2)])->sortByDesc('total'),
        ];
    }

    public function guardar(?CajaChicaMovimiento $movimiento, array $datos): CajaChicaMovimiento
    {
        return DB::transaction(function () use ($movimiento, $datos) {
            if ($datos['tipo'] === CajaChicaMovimiento::REPOSICION) {
                $datos = array_merge($datos, ['proveedor' => null, 'ruc' => null, 'vehiculo_id' => null, 'chofer_id' => null]);
            }
            if ($movimiento) {
                $movimiento->update($datos);
            } else {
                $movimiento = CajaChicaMovimiento::create($datos + ['user_id' => auth()->id()]);
            }
            $this->sincronizarCajaGeneral($movimiento);

            return $movimiento;
        });
    }

    public function eliminar(CajaChicaMovimiento $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            $this->caja->eliminarPara($movimiento);
            $movimiento->delete();
        });
    }

    /** La reposición sale de la caja general; un gasto de caja chica no toca la caja general. */
    private function sincronizarCajaGeneral(CajaChicaMovimiento $m): void
    {
        if (! $m->esReposicion()) {
            $this->caja->eliminarPara($m);

            return;
        }
        $this->caja->registrarPara($m, CajaMovimiento::EGRESO, CategoriaCaja::CajaChica, (float) $m->monto, $m->fecha, 'Reposición de caja chica · '.$m->descripcion);
    }

    /** Saldo que debería haber en la caja indicada al cierre del día (para el arqueo). */
    public function saldoSistema(string $caja, Carbon $fecha): float
    {
        return $caja === 'chica' ? $this->saldoAl($fecha) : $this->caja->saldoAl($fecha);
    }

    /**
     * Totales del conteo por denominación.
     *
     * @param  array<string, int|string|null>  $cantidades  denominación => cantidad
     * @return array{detalle: array<string, int>, billetes: float, monedas: float, total: float}
     */
    public static function contar(array $cantidades): array
    {
        $subtotal = ['billetes' => 0.0, 'monedas' => 0.0];
        $detalle = [];
        foreach (config('erp.denominaciones') as $tipo => $valores) {
            foreach ($valores as $valor) {
                $clave = self::clave($valor);
                $cantidad = max(0, (int) ($cantidades[$clave] ?? 0));
                $detalle[$clave] = $cantidad;
                $subtotal[$tipo] += $cantidad * $valor;
            }
        }

        return [
            'detalle' => $detalle,
            'billetes' => round($subtotal['billetes'], 2),
            'monedas' => round($subtotal['monedas'], 2),
            'total' => round($subtotal['billetes'] + $subtotal['monedas'], 2),
        ];
    }

    public static function clave(float|int $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    public function registrarArqueo(Carbon $fecha, string $caja, array $cantidades, ?string $observaciones): Arqueo
    {
        $conteo = self::contar($cantidades);
        $sistema = $this->saldoSistema($caja, $fecha);

        return Arqueo::updateOrCreate(
            ['fecha' => $fecha->toDateString(), 'caja' => $caja],
            [
                'detalle' => $conteo['detalle'],
                'total_contado' => $conteo['total'],
                'saldo_sistema' => $sistema,
                'diferencia' => round($conteo['total'] - $sistema, 2),
                'observaciones' => $observaciones,
                'user_id' => auth()->id(),
            ],
        );
    }
}
