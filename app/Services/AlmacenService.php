<?php

namespace App\Services;

use App\Enums\EstadoLiquidacion;
use App\Models\Chofer;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\LiquidacionItem;
use App\Models\Parte;
use App\Models\ParteFila;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reglas del parte diario de almacén.
 *
 *   Stock final = stock inicial (final del día anterior) + ingresos − salidas
 *
 * Llenos y vacíos se controlan por separado, como en la hoja de logística.
 */
class AlmacenService
{
    /** Llave de stock => [tipo, columna, título] */
    public const STOCK = [
        'lleno_s10' => ['lleno', 's10', 'S-10'],
        'lleno_s45' => ['lleno', 's45', 'S-45'],
        'lleno_m10' => ['lleno', 'm10', 'M-10'],
        'cambio_s10' => ['lleno', 'cambio_s10', 'Cambio S-10'],
        'cambio_s45' => ['lleno', 'cambio_s45', 'Cambio S-45'],
        'cambio_m10' => ['lleno', 'cambio_m10', 'Cambio M-10'],
        'plomo_s10' => ['vacio', 's10', 'Plomo S-10'],
        'plomo_s45' => ['vacio', 's45', 'Plomo S-45'],
        'color_s10' => ['vacio', 'color_s10', 'Color S-10'],
        'color_s45' => ['vacio', 'color_s45', 'Color S-45'],
    ];

    /**
     * Stock al cierre de una fecha (o antes de ella si $incluirDia = false).
     *
     * @return array<string, int> llave de STOCK => cantidad
     */
    public function stockAl(Carbon $fecha, bool $incluirDia = true): array
    {
        $totales = $this->sumasPorBloque(fn ($q) => $q->where('partes.fecha', $incluirDia ? '<=' : '<', $fecha->toDateString()));

        return $this->aStock($totales);
    }

    /** Resumen del día: stock inicial, ingresos, salidas y final (como "CONTROL DE STOCK"). */
    public function controlDelDia(Carbon $fecha): array
    {
        $inicial = $this->stockAl($fecha, false);
        $delDia = $this->sumasPorBloque(fn ($q) => $q->where('partes.fecha', $fecha->toDateString()));

        $filas = [];
        foreach (self::STOCK as $llave => [$tipo, $columna, $titulo]) {
            $ingreso = (int) ($delDia[$tipo.'_ingreso'][$columna] ?? 0);
            $salida = (int) ($delDia[$tipo.'_salida'][$columna] ?? 0);
            $filas[$llave] = [
                'titulo' => $titulo, 'tipo' => $tipo,
                'inicial' => $inicial[$llave], 'ingreso' => $ingreso, 'salida' => $salida,
                'final' => $inicial[$llave] + $ingreso - $salida,
            ];
        }

        return $filas;
    }

    /**
     * Control de masa con la planta: por placa, los vacíos que salieron a planta
     * deben regresar como llenos (más lo que la planta no aceptó).
     */
    public function controlDeMasa(Parte $parte): Collection
    {
        $parte->loadMissing('filas.empresa', 'filas.instalacion');
        $planta = $parte->filas->filter(fn (ParteFila $f) => $f->esPlanta()
            && in_array($f->bloque, [ParteFila::VACIO_SALIDA, ParteFila::LLENO_INGRESO], true));

        return $planta->groupBy(fn (ParteFila $f) => mb_strtoupper(trim($f->placa ?: $f->responsable ?: 'SIN PLACA')))
            ->map(function (Collection $filas, string $placa) {
                $salen = $filas->where('bloque', ParteFila::VACIO_SALIDA);
                $entran = $filas->where('bloque', ParteFila::LLENO_INGRESO);
                $vacios = $salen->sum(fn ($f) => $f->s10 + $f->s45 + $f->color_s10 + $f->color_s45);
                $llenos = $entran->sum(fn ($f) => $f->s10 + $f->s45 + $f->m10);

                return [
                    'placa' => $placa,
                    'responsable' => $filas->pluck('responsable')->filter()->unique()->join(', '),
                    'empresa' => $filas->map(fn ($f) => $f->empresa?->nombre)->filter()->unique()->join(', '),
                    'guias' => $filas->pluck('numero_guia')->filter()->unique()->join(', '),
                    'vacios' => $vacios,
                    'llenos' => $llenos,
                    'diferencia' => $llenos - $vacios,
                ];
            })->values();
    }

    /**
     * Cuadre con las ventas: lo que cada chofer sacó lleno menos lo que devolvió lleno
     * debería ser lo que se liquidó ese día.
     */
    public function cuadreChoferes(Carbon $fecha): Collection
    {
        $filas = ParteFila::query()
            ->join('partes', 'partes.id', '=', 'parte_filas.parte_id')
            ->where('partes.fecha', $fecha->toDateString())
            ->whereNotNull('parte_filas.chofer_id')
            ->whereIn('parte_filas.bloque', [ParteFila::LLENO_SALIDA, ParteFila::LLENO_INGRESO])
            ->where(fn ($q) => $q->whereNull('parte_filas.lugar')->orWhereRaw('UPPER(parte_filas.lugar) NOT LIKE ?', ['%PLANTA%']))
            ->selectRaw('parte_filas.chofer_id, parte_filas.bloque, SUM(parte_filas.s10) as s10, SUM(parte_filas.s45) as s45, SUM(parte_filas.m10) as m10')
            ->groupBy('parte_filas.chofer_id', 'parte_filas.bloque')
            ->get();

        $vendidoLiquidacion = LiquidacionItem::query()
            ->join('liquidaciones', 'liquidaciones.id', '=', 'liquidacion_items.liquidacion_id')
            ->join('productos', 'productos.id', '=', 'liquidacion_items.producto_id')
            ->whereNull('liquidaciones.deleted_at')
            ->where('liquidaciones.estado', '!=', EstadoLiquidacion::Anulada->value)
            ->where('liquidaciones.fecha_venta', $fecha->toDateString())
            ->whereIn('productos.codigo', ['S10', 'S45', 'M10'])
            ->selectRaw('liquidaciones.chofer_id, productos.codigo, SUM(liquidacion_items.cantidad) as cantidad')
            ->groupBy('liquidaciones.chofer_id', 'productos.codigo')
            ->get()
            ->groupBy('chofer_id');

        $choferes = Chofer::whereIn('id', $filas->pluck('chofer_id')->merge($vendidoLiquidacion->keys())->unique())->get()->keyBy('id');

        return $choferes->map(function (Chofer $chofer) use ($filas, $vendidoLiquidacion) {
            $salida = $filas->where('chofer_id', $chofer->id)->firstWhere('bloque', ParteFila::LLENO_SALIDA);
            $retorno = $filas->where('chofer_id', $chofer->id)->firstWhere('bloque', ParteFila::LLENO_INGRESO);
            $liq = ($vendidoLiquidacion[$chofer->id] ?? collect())->pluck('cantidad', 'codigo');
            $productos = [];
            foreach (['S10' => 's10', 'S45' => 's45', 'M10' => 'm10'] as $codigo => $col) {
                $salio = (int) ($salida->{$col} ?? 0);
                $volvio = (int) ($retorno->{$col} ?? 0);
                $productos[$codigo] = ['salio' => $salio, 'volvio' => $volvio, 'vendido' => $salio - $volvio, 'liquidado' => (int) ($liq[$codigo] ?? 0)];
            }

            return ['chofer' => $chofer, 'productos' => $productos];
        })->sortBy(fn ($c) => $c['chofer']->alias)->values();
    }

    /**
     * Stock disponible por empresa (como el cuadro "STOCK DISPONIBLE" del REGISTRO):
     * compras en planta de cada empresa menos lo vendido por esa empresa.
     *
     * @return array<string, array<string, int>> empresa => [S10, S45, M10]
     */
    public function disponiblePorEmpresa(?Carbon $hasta = null): array
    {
        $hasta ??= today();
        $compras = ParteFila::query()
            ->join('partes', 'partes.id', '=', 'parte_filas.parte_id')
            ->where('partes.fecha', '<=', $hasta->toDateString())
            ->where('parte_filas.bloque', ParteFila::LLENO_INGRESO)
            ->whereNotNull('parte_filas.empresa_id')
            ->selectRaw('parte_filas.empresa_id, SUM(parte_filas.s10) as s10, SUM(parte_filas.s45) as s45, SUM(parte_filas.m10) as m10')
            ->groupBy('parte_filas.empresa_id')->get()->keyBy('empresa_id');

        $ventas = LiquidacionItem::query()
            ->join('liquidaciones', 'liquidaciones.id', '=', 'liquidacion_items.liquidacion_id')
            ->join('productos', 'productos.id', '=', 'liquidacion_items.producto_id')
            ->whereNull('liquidaciones.deleted_at')
            ->where('liquidaciones.estado', '!=', EstadoLiquidacion::Anulada->value)
            ->where('liquidaciones.historico', false)
            ->where('liquidaciones.fecha_venta', '<=', $hasta->toDateString())
            ->whereIn('productos.codigo', ['S10', 'S45', 'M10'])
            ->selectRaw('liquidacion_items.empresa_id, productos.codigo, SUM(liquidacion_items.cantidad) as cantidad')
            ->groupBy('liquidacion_items.empresa_id', 'productos.codigo')->get()->groupBy('empresa_id');

        $resultado = [];
        foreach (Empresa::activas()->get() as $empresa) {
            $c = $compras[$empresa->id] ?? null;
            $v = ($ventas[$empresa->id] ?? collect())->pluck('cantidad', 'codigo');
            $resultado[$empresa->nombre] = [
                'S10' => (int) ($c->s10 ?? 0) - (int) ($v['S10'] ?? 0),
                'S45' => (int) ($c->s45 ?? 0) - (int) ($v['S45'] ?? 0),
                'M10' => (int) ($c->m10 ?? 0) - (int) ($v['M10'] ?? 0),
            ];
        }

        return $resultado;
    }

    /** Movimientos de una llave de stock entre dos fechas, con saldo acumulado. */
    public function kardex(string $llave, Carbon $desde, Carbon $hasta): array
    {
        [$tipo, $columna] = self::STOCK[$llave];
        $inicial = $this->stockAl($desde, false)[$llave];

        $movimientos = ParteFila::query()
            ->join('partes', 'partes.id', '=', 'parte_filas.parte_id')
            ->whereBetween('partes.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('parte_filas.bloque', [$tipo.'_ingreso', $tipo.'_salida'])
            ->where("parte_filas.$columna", '>', 0)
            ->orderBy('partes.fecha')->orderByRaw("CASE WHEN parte_filas.bloque LIKE '%ingreso' THEN 0 ELSE 1 END")->orderBy('parte_filas.orden')
            ->get(['parte_filas.*', 'partes.fecha as fecha_parte']);

        $saldo = $inicial;
        foreach ($movimientos as $m) {
            $cantidad = (int) $m->{$columna};
            $m->entrada = str_ends_with($m->bloque, 'ingreso') ? $cantidad : 0;
            $m->salida = str_ends_with($m->bloque, 'salida') ? $cantidad : 0;
            $saldo += $m->entrada - $m->salida;
            $m->saldo = $saldo;
        }

        return ['inicial' => $inicial, 'movimientos' => $movimientos, 'final' => $saldo];
    }

    /**
     * Guarda todas las filas de un parte (reemplaza las anteriores).
     * Relaciona responsable → chofer y placa → vehículo cuando coinciden.
     */
    public function guardar(Carbon $fecha, array $filas, ?string $observaciones): Parte
    {
        return DB::transaction(function () use ($fecha, $filas, $observaciones) {
            $parte = Parte::firstOrCreate(['fecha' => $fecha->toDateString()], ['estado' => Parte::ABIERTO, 'user_id' => Auth::id()]);
            abort_unless($parte->esEditable(), 422, 'El parte del '.$fecha->format('d/m/Y').' está cerrado. Reábrelo para modificarlo.');

            $parte->update(['observaciones' => $observaciones]);
            $choferes = Chofer::pluck('id', 'alias')->mapWithKeys(fn ($id, $alias) => [mb_strtoupper($alias) => $id]);
            $vehiculos = Vehiculo::pluck('id', 'placa')->mapWithKeys(fn ($id, $placa) => [mb_strtoupper($placa) => $id]);

            $parte->filas()->delete();
            foreach ($filas as $i => $fila) {
                $placa = $this->texto($fila['placa'] ?? null);
                $responsable = $this->texto($fila['responsable'] ?? null);
                $parte->filas()->create([
                    'bloque' => $fila['bloque'],
                    'orden' => $i,
                    'placa' => $placa,
                    'vehiculo_id' => $placa ? ($vehiculos[$placa] ?? null) : null,
                    'responsable' => $responsable,
                    'chofer_id' => $responsable ? ($choferes[$responsable] ?? null) : null,
                    'lugar' => $this->texto($fila['lugar'] ?? null),
                    'empresa_id' => $fila['empresa_id'] ?? null,
                    'instalacion_id' => $fila['instalacion_id'] ?? null,
                    'numero_guia' => $this->texto($fila['numero_guia'] ?? null),
                    's10' => (int) ($fila['s10'] ?? 0),
                    's45' => (int) ($fila['s45'] ?? 0),
                    'm10' => (int) ($fila['m10'] ?? 0),
                    'cambio_s10' => (int) ($fila['cambio_s10'] ?? 0),
                    'cambio_s45' => (int) ($fila['cambio_s45'] ?? 0),
                    'cambio_m10' => (int) ($fila['cambio_m10'] ?? 0),
                    'color_s10' => (int) ($fila['color_s10'] ?? 0),
                    'color_s45' => (int) ($fila['color_s45'] ?? 0),
                    'observacion' => $fila['observacion'] ?? null,
                ]);
            }
            $parte->touch();

            return $parte;
        });
    }

    /**
     * Ajusta el stock a un conteo físico: crea filas de ajuste por la diferencia.
     *
     * @param  array<string, int|null>  $conteo  llave de STOCK => cantidad contada
     */
    public function ajustarAConteo(Parte $parte, array $conteo): int
    {
        $final = collect($this->controlDelDia($parte->fecha))->map->final;
        $ajustes = ['lleno_ingreso' => [], 'lleno_salida' => [], 'vacio_ingreso' => [], 'vacio_salida' => []];
        foreach ($conteo as $llave => $contado) {
            if ($contado === null || $contado === '' || ! isset(self::STOCK[$llave])) {
                continue;
            }
            [$tipo, $columna] = self::STOCK[$llave];
            $diferencia = (int) $contado - $final[$llave];
            if ($diferencia !== 0) {
                $ajustes[$tipo.($diferencia > 0 ? '_ingreso' : '_salida')][$columna] = abs($diferencia);
            }
        }

        $creadas = 0;
        $orden = (int) $parte->filas()->max('orden') + 1;
        foreach ($ajustes as $bloque => $columnas) {
            if ($columnas === []) {
                continue;
            }
            $parte->filas()->create($columnas + ['bloque' => $bloque, 'orden' => $orden++, 'lugar' => 'AJUSTE', 'responsable' => 'INVENTARIO', 'observacion' => 'Ajuste a conteo físico']);
            $creadas++;
        }

        return $creadas;
    }

    /** Instalación que corresponde a una placa según el cuadro de instalaciones. */
    public function instalacionPorPlaca(?string $placa): ?Instalacion
    {
        if (! $placa) {
            return null;
        }

        return Instalacion::activas()->get()->first(fn (Instalacion $i) => in_array(mb_strtoupper($placa), $i->listaPlacas(), true));
    }

    private function sumasPorBloque(callable $filtro): array
    {
        $query = ParteFila::query()->join('partes', 'partes.id', '=', 'parte_filas.parte_id');
        $filtro($query);
        $rows = $query->selectRaw('parte_filas.bloque, SUM(s10) as s10, SUM(s45) as s45, SUM(m10) as m10, SUM(cambio_s10) as cambio_s10, SUM(cambio_s45) as cambio_s45, SUM(cambio_m10) as cambio_m10, SUM(color_s10) as color_s10, SUM(color_s45) as color_s45')
            ->groupBy('parte_filas.bloque')->get();

        $totales = [];
        foreach ($rows as $row) {
            $totales[$row->bloque] = collect($row->getAttributes())->except('bloque')->map(fn ($v) => (int) $v)->all();
        }

        return $totales;
    }

    private function aStock(array $totales): array
    {
        $stock = [];
        foreach (self::STOCK as $llave => [$tipo, $columna]) {
            $stock[$llave] = (int) ($totales[$tipo.'_ingreso'][$columna] ?? 0) - (int) ($totales[$tipo.'_salida'][$columna] ?? 0);
        }

        return $stock;
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_strtoupper($valor);
    }
}
