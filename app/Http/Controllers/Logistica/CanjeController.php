<?php

namespace App\Http\Controllers\Logistica;

use App\Http\Controllers\Controller;
use App\Http\Requests\CanjeRequest;
use App\Models\Canje;
use App\Models\Producto;
use App\Services\LogisticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Canje de vacíos de color (otras marcas) por vacíos plomo de Solgas. */
class CanjeController extends Controller
{
    public function __construct(private readonly LogisticaService $logistica) {}

    public function index(Request $request)
    {
        $canjes = Canje::with(['producto', 'user'])
            ->when($request->q, fn ($q, $t) => $q->where('contraparte', 'like', "%$t%"))
            ->when($request->desde, fn ($q, $d) => $q->where('fecha', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->where('fecha', '<=', $h))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        return $this->tableOrPage($request, 'logistica.canjes.index', 'logistica.canjes._table', compact('canjes'));
    }

    public function create(): View
    {
        return $this->form(new Canje(['fecha' => today()]));
    }

    public function store(CanjeRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            $canje = Canje::create($request->validated() + ['user_id' => Auth::id()]);
            $this->logistica->aplicarCanje($canje);
        });

        return $this->ok('Canje registrado.');
    }

    public function show(Canje $canje): View
    {
        $canje->load(['producto', 'user', 'movimientos.producto']);

        return view('logistica.canjes.show', compact('canje'));
    }

    public function edit(Canje $canje): View
    {
        return $this->form($canje);
    }

    public function update(CanjeRequest $request, Canje $canje): JsonResponse
    {
        DB::transaction(function () use ($request, $canje) {
            $canje->update($request->validated());
            $this->logistica->aplicarCanje($canje);
        });

        return $this->ok('Canje actualizado.');
    }

    public function destroy(Canje $canje): JsonResponse
    {
        DB::transaction(function () use ($canje) {
            $canje->movimientos()->delete();
            $canje->delete();
        });

        return $this->ok('Canje eliminado y stock revertido.');
    }

    private function form(Canje $canje): View
    {
        return view('logistica.canjes.form', ['canje' => $canje, 'envases' => Producto::envases()->get()->mapWithKeys(fn ($p) => [$p->id => "Balón {$p->capacidad_kg} kg"])]);
    }
}
