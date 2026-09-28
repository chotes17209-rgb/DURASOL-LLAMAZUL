<?php

namespace App\Http\Controllers\Caja;

use App\Enums\CategoriaCaja;
use App\Http\Controllers\Controller;
use App\Http\Requests\DepositoRequest;
use App\Models\CajaMovimiento;
use App\Models\Chofer;
use App\Models\CuentaBancaria;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Services\CajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Depósitos bancarios del efectivo de caja (salen de caja). */
class DepositoController extends Controller
{
    public function __construct(private readonly CajaService $caja) {}

    public function index(Request $request)
    {
        $depositos = Deposito::with(['cuentaBancaria', 'empresa', 'chofer', 'user'])
            ->when($request->cuenta_bancaria_id, fn ($q, $c) => $q->where('cuenta_bancaria_id', $c))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->whereLike('depositante', "%$t%")->orWhereLike('numero_operacion', "%$t%")))
            ->when($request->desde, fn ($q, $d) => $q->where('fecha', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->where('fecha', '<=', $h))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(30)->withQueryString();

        $cuentas = CuentaBancaria::activas()->get()->mapWithKeys(fn ($c) => [$c->id => $c->nombreMostrar()]);
        $totalMes = (float) Deposito::where('fecha', '>=', today()->startOfMonth()->toDateString())->sum('monto');

        return $this->tableOrPage($request, 'caja.depositos.index', 'caja.depositos._table', compact('depositos', 'cuentas', 'totalMes'));
    }

    public function create(): View
    {
        return $this->form(new Deposito(['fecha' => today()]));
    }

    public function store(DepositoRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            $deposito = Deposito::create($request->validated() + ['user_id' => auth()->id()]);
            $this->sincronizarCaja($deposito);
        });

        return $this->ok('Depósito registrado y descontado de caja.');
    }

    public function show(Deposito $deposito): View
    {
        $deposito->load(['cuentaBancaria', 'empresa', 'chofer', 'user']);

        return view('caja.depositos.show', compact('deposito'));
    }

    public function edit(Deposito $deposito): View
    {
        return $this->form($deposito);
    }

    public function update(DepositoRequest $request, Deposito $deposito): JsonResponse
    {
        DB::transaction(function () use ($request, $deposito) {
            $deposito->update($request->validated());
            $this->sincronizarCaja($deposito);
        });

        return $this->ok('Depósito actualizado.');
    }

    public function destroy(Deposito $deposito): JsonResponse
    {
        DB::transaction(function () use ($deposito) {
            $this->caja->eliminarPara($deposito);
            $deposito->delete();
        });

        return $this->ok('Depósito eliminado; el monto regresó a caja.');
    }

    private function sincronizarCaja(Deposito $deposito): void
    {
        $deposito->load('cuentaBancaria');
        $this->caja->registrarPara(
            $deposito, CajaMovimiento::EGRESO, CategoriaCaja::Deposito, (float) $deposito->monto, $deposito->fecha,
            'Depósito '.$deposito->cuentaBancaria?->nombreMostrar().($deposito->depositante ? " · {$deposito->depositante}" : ''),
            $deposito->empresa_id ?? $deposito->cuentaBancaria?->empresa_id,
        );
    }

    private function form(Deposito $deposito): View
    {
        return view('caja.depositos.form', [
            'deposito' => $deposito,
            'cuentas' => CuentaBancaria::activas()->get()->mapWithKeys(fn ($c) => [$c->id => $c->nombreMostrar()]),
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
            'choferes' => Chofer::activos()->pluck('alias', 'id'),
        ]);
    }
}
