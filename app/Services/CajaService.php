<?php

namespace App\Services;

use App\Enums\CategoriaCaja;
use App\Models\CajaMovimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CajaService
{
    /** Crea o actualiza el movimiento de caja que corresponde a un documento. */
    public function registrarPara(Model $origen, string $tipo, CategoriaCaja $categoria, float $monto, Carbon|string $fecha, string $descripcion, ?int $empresaId = null): ?CajaMovimiento
    {
        $existente = CajaMovimiento::where('origen_type', $origen->getMorphClass())->where('origen_id', $origen->getKey())->first();

        if (round($monto, 2) == 0.0) {
            $existente?->delete();

            return null;
        }

        $datos = [
            'fecha' => Carbon::parse($fecha)->toDateString(),
            'tipo' => $tipo,
            'categoria' => $categoria,
            'monto' => round($monto, 2),
            'empresa_id' => $empresaId,
            'descripcion' => mb_substr($descripcion, 0, 255),
        ];

        if ($existente) {
            $existente->update($datos);

            return $existente;
        }

        return CajaMovimiento::create($datos + [
            'origen_type' => $origen->getMorphClass(),
            'origen_id' => $origen->getKey(),
            'user_id' => Auth::id(),
        ]);
    }

    public function eliminarPara(Model $origen): void
    {
        CajaMovimiento::where('origen_type', $origen->getMorphClass())->where('origen_id', $origen->getKey())
            ->get()->each->delete();
    }

    public function saldoAl(Carbon $fecha): float
    {
        $ingresos = (float) CajaMovimiento::where('tipo', CajaMovimiento::INGRESO)->where('fecha', '<=', $fecha->toDateString())->sum('monto');
        $egresos = (float) CajaMovimiento::where('tipo', CajaMovimiento::EGRESO)->where('fecha', '<=', $fecha->toDateString())->sum('monto');

        return round($ingresos - $egresos, 2);
    }

    /** Resumen de un rango: saldo inicial, ingresos y egresos por categoría, saldo final. */
    public function resumen(Carbon $desde, Carbon $hasta): array
    {
        $saldoInicial = $this->saldoAl($desde->copy()->subDay());

        $porCategoria = CajaMovimiento::query()
            ->selectRaw('tipo, categoria, SUM(monto) as total, COUNT(*) as cantidad')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('tipo', 'categoria')
            ->get();

        $ingresos = (float) $porCategoria->where('tipo', CajaMovimiento::INGRESO)->sum('total');
        $egresos = (float) $porCategoria->where('tipo', CajaMovimiento::EGRESO)->sum('total');

        return [
            'saldo_inicial' => $saldoInicial,
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'saldo_final' => round($saldoInicial + $ingresos - $egresos, 2),
            'por_categoria' => $porCategoria,
        ];
    }
}
