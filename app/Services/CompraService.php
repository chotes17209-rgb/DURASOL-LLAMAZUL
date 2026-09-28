<?php

namespace App\Services;

use App\Models\CompraPlanta;
use App\Models\CuotaCompra;
use App\Models\Empresa;
use App\Models\Parte;
use App\Models\ParteFila;
use App\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Compras de balones llenos en planta (Solgas / Masgas).
 *
 * Origen de los datos: registro manual, ingresos de llenos desde planta del parte diario
 * (se sincronizan al guardar el parte) e importación del registro de compras.
 * Base 10 kg: S10 + M10 + S45 × 4.5 (un balón de 45 kg equivale a 4.5 de 10 kg).
 */
class CompraService
{
    public const PRODUCTOS = ['S10', 'S45', 'M10'];

    public const FACTOR_10KG = ['S10' => 1, 'S45' => 4.5, 'M10' => 1];

    public function __construct(private readonly CostoService $costos) {}

    /**
     * Cuadro mensual por empresa (null = global): compras diarias, cuota, avance y comparativa con el mes anterior.
     */
    public function cuadro(Carbon $mes, ?int $empresaId): array
    {
        $desde = $mes->copy()->startOfMonth();
        $hasta = $mes->copy()->endOfMonth();
        $anterior = $desde->copy()->subMonthNoOverflow();
        $productos = Producto::whereIn('codigo', self::PRODUCTOS)->pluck('codigo', 'id');

        $diario = $this->cantidadesPorDia($desde, $hasta, $empresaId, $productos);
        $dias = collect(range(0, $desde->diffInDays($hasta)))->map(function ($n) use ($desde, $diario) {
            $fecha = $desde->copy()->addDays($n);

            return ['fecha' => $fecha, 'cantidades' => $diario[$fecha->toDateString()] ?? []];
        });
        $total = collect(self::PRODUCTOS)->mapWithKeys(fn ($c) => [$c => (int) $dias->sum(fn ($d) => $d['cantidades'][$c] ?? 0)])->all();

        $totalAnterior = collect(self::PRODUCTOS)->mapWithKeys(fn ($c) => [$c => 0])->all();
        foreach ($this->cantidadesPorDia($anterior, $anterior->copy()->endOfMonth(), $empresaId, $productos) as $cantidades) {
            foreach ($cantidades as $c => $n) {
                $totalAnterior[$c] += $n;
            }
        }

        $cuotas = CuotaCompra::where('mes', $desde->format('Y-m'))->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->get()->groupBy(fn ($c) => $productos[$c->producto_id] ?? null)->map->sum('cantidad');

        $cuota = collect(self::PRODUCTOS)->mapWithKeys(function ($c) use ($cuotas, $total) {
            $meta = (int) ($cuotas[$c] ?? 0);

            return [$c => ['cuota' => $meta, 'avance' => $total[$c], 'diferencia' => $meta - $total[$c], 'porcentaje' => $meta ? round($total[$c] / $meta * 100, 2) : null]];
        })->all();

        $comparativa = collect(self::PRODUCTOS)->mapWithKeys(fn ($c) => [$c => [
            'anterior' => $totalAnterior[$c], 'actual' => $total[$c], 'variacion' => $total[$c] - $totalAnterior[$c],
            'porcentaje' => $totalAnterior[$c] ? round($total[$c] / $totalAnterior[$c] * 100, 2) : null,
        ]])->all();
        $base = fn (array $t) => (int) round(array_sum(array_map(fn ($c) => ($t[$c] ?? 0) * self::FACTOR_10KG[$c], self::PRODUCTOS)));
        $comparativa['TOTAL'] = [
            'anterior' => $base($totalAnterior), 'actual' => $base($total), 'variacion' => $base($total) - $base($totalAnterior),
            'porcentaje' => $base($totalAnterior) ? round($base($total) / $base($totalAnterior) * 100, 2) : null,
        ];

        return [
            'mes' => $desde, 'anterior' => $anterior, 'dias' => $dias, 'total' => $total,
            'cuota' => $cuota, 'comparativa' => $comparativa,
            'base10' => ['anterior' => $base($totalAnterior), 'actual' => $base($total)],
            'dias_con_compra' => $dias->filter(fn ($d) => array_sum($d['cantidades']) > 0)->count(),
        ];
    }

    /** @return array<string, array<string, int>> fecha => código => cantidad */
    private function cantidadesPorDia(Carbon $desde, Carbon $hasta, ?int $empresaId, Collection $productos): array
    {
        $r = [];
        CompraPlanta::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereIn('producto_id', $productos->keys())
            ->selectRaw('fecha, producto_id, SUM(cantidad) as cantidad')->groupBy('fecha', 'producto_id')->get()
            ->each(function ($c) use (&$r, $productos) {
                $r[$c->fecha->toDateString()][$productos[$c->producto_id]] = (int) $c->cantidad;
            });

        return $r;
    }

    /**
     * Pasa a compras los ingresos de llenos desde planta del parte (una compra por fila y presentación),
     * con el precio vigente de la instalación. Las fechas cubiertas por el registro importado no se tocan.
     */
    public function sincronizarParte(Parte $parte): void
    {
        $fecha = $parte->fecha->toDateString();
        CompraPlanta::where('origen', 'parte')->where('fecha', $fecha)->delete();
        if ($fecha <= config('erp.compras.importadas_hasta')) {
            return;
        }
        $productos = Producto::whereIn('codigo', self::PRODUCTOS)->pluck('id', 'codigo');
        $parte->loadMissing('filas.instalacion');
        foreach ($parte->filas as $f) {
            if ($f->bloque !== ParteFila::LLENO_INGRESO || (! $f->instalacion_id && ! $f->esPlanta())) {
                continue;
            }
            $empresa = $f->empresa_id ?? $f->instalacion?->empresa_id;
            if (! $empresa) {
                continue;
            }
            foreach (['S10' => $f->s10, 'S45' => $f->s45, 'M10' => $f->m10] as $codigo => $cantidad) {
                if ($cantidad > 0) {
                    CompraPlanta::create([
                        'fecha' => $fecha, 'empresa_id' => $empresa, 'producto_id' => $productos[$codigo], 'instalacion_id' => $f->instalacion_id,
                        'cantidad' => $cantidad, 'documento' => $f->numero_guia, 'origen' => 'parte',
                        'precio_unitario' => $this->precio($empresa, $productos[$codigo], $f->instalacion_id, $fecha),
                        'observacion' => trim(($f->placa ?? '').' '.($f->responsable ?? '')) ?: null,
                    ]);
                }
            }
        }
    }

    /** Precio de compra: el de la instalación a la fecha; si no hay, el promedio de la empresa. */
    public function precio(int $empresaId, int $productoId, ?int $instalacionId, string $fecha): ?float
    {
        if ($instalacionId) {
            $precio = app(PrecioService::class)->precioCompra($instalacionId, $productoId, $fecha);
            if ($precio !== null) {
                return $precio;
            }
        }

        return $this->costos->costoUnitario($empresaId, $productoId, $fecha);
    }

    public function empresas(): Collection
    {
        return Empresa::activas()->orderBy('nombre')->get();
    }
}
