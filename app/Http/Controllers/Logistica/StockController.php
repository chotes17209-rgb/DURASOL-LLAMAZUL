<?php

namespace App\Http\Controllers\Logistica;

use App\Http\Controllers\Controller;
use App\Services\AlmacenService;
use App\Support\Reporte;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private readonly AlmacenService $almacen) {}

    public function index(Request $request)
    {
        $fecha = $request->date('fecha') ?? today();

        if (in_array($request->formato, ['pdf', 'xlsx'], true)) {
            $control = $this->almacen->controlDelDia($fecha);

            return (new Reporte('Stock de almacén', 'Al '.$fecha->format('d/m/Y')))
                ->tabla(null, ['Concepto' => 'texto', 'Inicial' => 'entero', 'Ingreso' => 'entero', 'Salida' => 'entero', 'Final' => 'entero'],
                    collect($control)->map(fn ($f) => [($f['tipo'] === 'lleno' ? 'Llenos ' : 'Vacíos ').$f['titulo'], $f['inicial'], $f['ingreso'], $f['salida'], $f['final']])->values())
                ->tabla('Total (llenos + cambios)', ['S-10' => 'entero', 'S-45' => 'entero', 'M-10' => 'entero'],
                    [array_values(AlmacenService::totalesPorPresentacion($control))])
                ->tabla('Total vacíos (plomos + colores)', ['S-10' => 'entero', 'S-45' => 'entero'],
                    [array_values(AlmacenService::totalesVacios($control))])
                ->tabla('Stock disponible por empresa', ['Empresa' => 'texto', 'S-10' => 'entero', 'S-45' => 'entero', 'M-10' => 'entero'],
                    collect($this->almacen->disponiblePorEmpresa($fecha))->map(fn ($s, $e) => [$e, $s['S10'], $s['S45'], $s['M10']])->values())
                ->descargar($request->formato, 'stock-'.$fecha->toDateString());
        }

        return view('logistica.stock.index', [
            'fecha' => $fecha,
            'control' => $this->almacen->controlDelDia($fecha),
            'porEmpresa' => $this->almacen->disponiblePorEmpresa($fecha),
        ]);
    }

    public function kardex(Request $request)
    {
        $llave = array_key_exists((string) $request->llave, AlmacenService::STOCK) ? $request->llave : 'lleno_s10';
        $desde = $request->date('desde') ?? today()->startOfMonth();
        $hasta = $request->date('hasta') ?? today();
        $kardex = $this->almacen->kardex($llave, $desde, $hasta);

        return $this->tableOrPage($request, 'logistica.stock.kardex', 'logistica.stock._kardex', compact('llave', 'desde', 'hasta', 'kardex'));
    }
}
