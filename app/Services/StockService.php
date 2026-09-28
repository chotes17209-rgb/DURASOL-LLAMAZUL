<?php

namespace App\Services;

use App\Enums\EstadoStock;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\StockMovimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Kardex de balones. Todas las entradas y salidas pasan por aquí.
 *
 * Regla de empresas: los llenos y los cambios pertenecen a una empresa
 * (Durasol o Llamazul); los vacíos plomo y de color son un solo pool físico.
 */
class StockService
{
    /**
     * Reemplaza los movimientos de un documento por los nuevos.
     *
     * @param  array<int, array{fecha: mixed, empresa_id: ?int, producto_id: int, estado: EstadoStock, cantidad: int, concepto: string}>  $lineas
     * @param  bool  $validarDisponible  Evita que el stock quede negativo.
     */
    public function sincronizar(Model $origen, array $lineas, bool $validarDisponible = true): void
    {
        $this->revertir($origen);

        $lineas = array_values(array_filter($lineas, fn ($l) => (int) $l['cantidad'] !== 0));
        $lineas = array_map(fn ($l) => $this->normalizarLinea($l), $lineas);

        if ($validarDisponible) {
            $this->validarDisponible($lineas);
        }

        foreach ($lineas as $linea) {
            StockMovimiento::create($linea + [
                'origen_type' => $origen->getMorphClass(),
                'origen_id' => $origen->getKey(),
                'user_id' => Auth::id(),
            ]);
        }
    }

    public function revertir(Model $origen): void
    {
        StockMovimiento::where('origen_type', $origen->getMorphClass())
            ->where('origen_id', $origen->getKey())
            ->delete();
    }

    /** Saldo actual de un producto/estado (opcionalmente de una empresa). */
    public function saldo(int $productoId, EstadoStock $estado, ?int $empresaId = null): int
    {
        return (int) StockMovimiento::where('producto_id', $productoId)
            ->where('estado', $estado)
            ->when($this->usaEmpresa($estado), fn ($q) => $q->where('empresa_id', $empresaId))
            ->sum('cantidad');
    }

    /**
     * Saldos agrupados: [estado][producto_id][empresa_id|0] => cantidad.
     */
    public function saldos(?Carbon $hasta = null): array
    {
        $rows = StockMovimiento::query()
            ->selectRaw('estado, producto_id, empresa_id, SUM(cantidad) as total')
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta->toDateString()))
            ->groupBy('estado', 'producto_id', 'empresa_id')
            ->get();

        $saldos = [];
        foreach ($rows as $row) {
            $estado = $row->estado instanceof EstadoStock ? $row->estado->value : $row->estado;
            $saldos[$estado][$row->producto_id][(int) $row->empresa_id] = (int) $row->total;
        }

        return $saldos;
    }

    /**
     * Resumen listo para pantalla: llenos y cambios por empresa, vacíos por envase.
     */
    public function resumen(?Carbon $hasta = null): array
    {
        $saldos = $this->saldos($hasta);
        $empresas = Empresa::activas()->get();
        $productosGas = Producto::gas()->where('controla_stock', true)->get();
        $envases = Producto::envases()->get();

        $llenos = [];
        foreach ($productosGas as $producto) {
            $fila = ['producto' => $producto, 'empresas' => [], 'cambios' => [], 'total' => 0, 'total_cambios' => 0];
            foreach ($empresas as $empresa) {
                $lleno = $saldos['lleno'][$producto->id][$empresa->id] ?? 0;
                $cambio = $saldos['cambio'][$producto->id][$empresa->id] ?? 0;
                $fila['empresas'][$empresa->id] = $lleno;
                $fila['cambios'][$empresa->id] = $cambio;
                $fila['total'] += $lleno;
                $fila['total_cambios'] += $cambio;
            }
            $llenos[] = $fila;
        }

        $vacios = [];
        foreach ($envases as $envase) {
            $plomo = $saldos['vacio'][$envase->id][0] ?? 0;
            $color = $saldos['color'][$envase->id][0] ?? 0;
            $vacios[] = ['producto' => $envase, 'plomo' => $plomo, 'color' => $color, 'total' => $plomo + $color];
        }

        return compact('empresas', 'llenos', 'vacios');
    }

    /** Kardex con saldo acumulado para un producto/estado/empresa. */
    public function kardex(int $productoId, EstadoStock $estado, ?int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $base = StockMovimiento::where('producto_id', $productoId)
            ->where('estado', $estado)
            ->when($this->usaEmpresa($estado), fn ($q) => $q->where('empresa_id', $empresaId));

        $saldoInicial = (int) (clone $base)->where('fecha', '<', $desde->toDateString())->sum('cantidad');

        $movimientos = (clone $base)
            ->with(['empresa', 'user', 'origen'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')->orderBy('id')
            ->get();

        $saldo = $saldoInicial;
        foreach ($movimientos as $mov) {
            $saldo += $mov->cantidad;
            $mov->saldo = $saldo;
        }

        return ['saldo_inicial' => $saldoInicial, 'movimientos' => $movimientos, 'saldo_final' => $saldo];
    }

    /** Los vacíos (plomo y color) no se separan por empresa. */
    public function usaEmpresa(EstadoStock $estado): bool
    {
        return in_array($estado, [EstadoStock::Lleno, EstadoStock::Cambio], true);
    }

    private function normalizarLinea(array $linea): array
    {
        $estado = $linea['estado'] instanceof EstadoStock ? $linea['estado'] : EstadoStock::from($linea['estado']);

        return [
            'fecha' => Carbon::parse($linea['fecha'])->toDateString(),
            'empresa_id' => $this->usaEmpresa($estado) ? $linea['empresa_id'] : null,
            'producto_id' => $linea['producto_id'],
            'estado' => $estado,
            'cantidad' => (int) $linea['cantidad'],
            'concepto' => mb_substr($linea['concepto'], 0, 150),
        ];
    }

    /** @throws ValidationException si alguna salida deja el stock en negativo. */
    private function validarDisponible(array $lineas): void
    {
        $necesario = [];
        foreach ($lineas as $linea) {
            $key = $linea['estado']->value.'|'.$linea['producto_id'].'|'.($linea['empresa_id'] ?? 0);
            $necesario[$key] = ($necesario[$key] ?? 0) + $linea['cantidad'];
        }

        $errores = [];
        foreach ($necesario as $key => $cantidad) {
            if ($cantidad >= 0) {
                continue;
            }
            [$estado, $productoId, $empresaId] = explode('|', $key);
            $estado = EstadoStock::from($estado);
            $disponible = $this->saldo((int) $productoId, $estado, $empresaId ? (int) $empresaId : null);
            if ($disponible + $cantidad < 0) {
                $producto = Producto::find($productoId);
                $empresa = $empresaId ? Empresa::find($empresaId)?->nombre.' ' : '';
                $errores[] = sprintf(
                    'Stock insuficiente de %s %s(%s): hay %d y se intenta sacar %d.',
                    $producto?->codigo, $empresa, mb_strtolower($estado->label()), $disponible, -$cantidad
                );
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['stock' => $errores]);
        }
    }

    public function envaseDe(Producto $producto): int
    {
        return $producto->envase_id ?? $producto->id;
    }

    /** @return Collection<int, Producto> */
    public function productos(): Collection
    {
        return Producto::with('envase')->get()->keyBy('id');
    }
}
