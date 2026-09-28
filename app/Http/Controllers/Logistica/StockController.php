<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoGuia;
use App\Enums\EstadoStock;
use App\Http\Controllers\Controller;
use App\Models\Despacho;
use App\Models\Empresa;
use App\Models\Guia;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): View
    {
        $fecha = $request->date('fecha') ?? today();
        $resumen = $this->stock->resumen($fecha);

        // Movimiento del día por estado (entradas y salidas).
        $movDia = StockMovimiento::where('fecha', $fecha->toDateString())
            ->selectRaw('estado, SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entradas, SUM(CASE WHEN cantidad < 0 THEN -cantidad ELSE 0 END) as salidas')
            ->groupBy('estado')->get()->keyBy(fn ($r) => $r->estado->value);

        $guiasTransito = Guia::with(['empresa', 'detalles'])->where('estado', EstadoGuia::EnTransito)->get();
        $choferesEnRuta = Despacho::with(['chofer', 'detalles'])->where('estado', EstadoDespacho::EnRuta)->get();
        $ultimos = StockMovimiento::with(['producto', 'empresa', 'user'])->latest('id')->limit(15)->get();

        return view('logistica.stock.index', compact('fecha', 'resumen', 'movDia', 'guiasTransito', 'choferesEnRuta', 'ultimos'));
    }

    public function kardex(Request $request)
    {
        $productos = Producto::activos()->where('controla_stock', true)->get();
        $empresas = Empresa::activas()->get();
        $productoId = (int) ($request->producto_id ?: $productos->firstWhere('codigo', 'S10')?->id ?: $productos->first()?->id);
        $estado = EstadoStock::tryFrom((string) $request->estado) ?? EstadoStock::Lleno;
        $empresaId = $this->stock->usaEmpresa($estado) ? (int) ($request->empresa_id ?: $empresas->first()?->id) : null;
        $desde = $request->date('desde') ?? today()->startOfMonth();
        $hasta = $request->date('hasta') ?? today();

        $producto = Producto::find($productoId);
        if (! $this->stock->usaEmpresa($estado) && $producto?->envase_id) {
            $productoId = $producto->envase_id;
        }

        $kardex = $this->stock->kardex($productoId, $estado, $empresaId, Carbon::parse($desde), Carbon::parse($hasta));
        $data = compact('productos', 'empresas', 'productoId', 'estado', 'empresaId', 'desde', 'hasta', 'kardex');

        return $this->tableOrPage($request, 'logistica.stock.kardex', 'logistica.stock._kardex', $data);
    }
}
