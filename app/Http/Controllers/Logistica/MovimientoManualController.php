<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\MovimientoManualRequest;
use App\Models\Empresa;
use App\Models\MovimientoStockManual;
use App\Models\Producto;
use App\Services\LogisticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Stock inicial, ingreso de vacíos que dejan clientes, préstamos, mermas y ajustes. */
class MovimientoManualController extends Controller
{
    public function __construct(private readonly LogisticaService $logistica) {}

    public function index(Request $request)
    {
        $movimientos = MovimientoStockManual::with(['producto', 'empresa', 'user'])
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->q, fn ($q, $t) => $q->where('referencia', 'like', "%$t%"))
            ->when($request->desde, fn ($q, $d) => $q->where('fecha', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->where('fecha', '<=', $h))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(30)->withQueryString();

        return $this->tableOrPage($request, 'logistica.movimientos.index', 'logistica.movimientos._table', compact('movimientos'));
    }

    public function create(): View
    {
        return $this->form(new MovimientoStockManual(['fecha' => today(), 'sentido' => 'entrada', 'estado' => EstadoStock::Vacio]));
    }

    public function store(MovimientoManualRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            $mov = MovimientoStockManual::create($this->datos($request) + ['user_id' => Auth::id()]);
            $this->logistica->aplicarManual($mov);
        });

        return $this->ok('Movimiento registrado.');
    }

    public function show(MovimientoStockManual $movimiento): View
    {
        $movimiento->load(['producto', 'empresa', 'user', 'movimientos.producto', 'movimientos.empresa']);

        return view('logistica.movimientos.show', compact('movimiento'));
    }

    public function edit(MovimientoStockManual $movimiento): View
    {
        return $this->form($movimiento);
    }

    public function update(MovimientoManualRequest $request, MovimientoStockManual $movimiento): JsonResponse
    {
        DB::transaction(function () use ($request, $movimiento) {
            $movimiento->update($this->datos($request));
            $this->logistica->aplicarManual($movimiento);
        });

        return $this->ok('Movimiento actualizado.');
    }

    public function destroy(MovimientoStockManual $movimiento): JsonResponse
    {
        DB::transaction(function () use ($movimiento) {
            $movimiento->movimientos()->delete();
            $movimiento->delete();
        });

        return $this->ok('Movimiento eliminado y stock revertido.');
    }

    /** Los vacíos se guardan contra su envase (10 kg / 45 kg) y sin empresa. */
    private function datos(MovimientoManualRequest $request): array
    {
        $data = $request->validated();
        $estado = EstadoStock::from($data['estado']);
        if (in_array($estado, [EstadoStock::Vacio, EstadoStock::Color], true)) {
            $producto = Producto::find($data['producto_id']);
            $data['producto_id'] = $producto->envase_id ?? $producto->id;
            $data['empresa_id'] = null;
        }

        return $data;
    }

    private function form(MovimientoStockManual $movimiento): View
    {
        return view('logistica.movimientos.form', [
            'movimiento' => $movimiento,
            'productos' => Producto::activos()->where('controla_stock', true)->get()->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre}"]),
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
        ]);
    }
}
