<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\CuentaBancariaRequest;
use App\Models\CuentaBancaria;
use App\Models\Deposito;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuentaBancariaController extends Controller
{
    public function index(Request $request)
    {
        $cuentas = CuentaBancaria::with('empresa')->withSum('depositos as total_depositado', 'monto')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->whereLike('banco', "%$t%")->orWhereLike('alias', "%$t%")))
            ->orderBy('banco')->paginate(25)->withQueryString();

        return $this->tableOrPage($request, 'caja.cuentas.index', 'caja.cuentas._table', compact('cuentas'));
    }

    public function create(): View
    {
        return $this->form(new CuentaBancaria(['moneda' => 'PEN', 'activo' => true]));
    }

    public function store(CuentaBancariaRequest $request): JsonResponse
    {
        CuentaBancaria::create($request->validated());

        return $this->ok('Cuenta bancaria registrada.');
    }

    public function show(CuentaBancaria $cuenta): View
    {
        $depositos = Deposito::where('cuenta_bancaria_id', $cuenta->id)->latest('fecha')->limit(20)->get();

        return view('caja.cuentas.show', compact('cuenta', 'depositos'));
    }

    public function edit(CuentaBancaria $cuenta): View
    {
        return $this->form($cuenta);
    }

    public function update(CuentaBancariaRequest $request, CuentaBancaria $cuenta): JsonResponse
    {
        $cuenta->update($request->validated());

        return $this->ok('Cuenta bancaria actualizada.');
    }

    public function destroy(CuentaBancaria $cuenta): JsonResponse
    {
        abort_if(Deposito::where('cuenta_bancaria_id', $cuenta->id)->exists(), 422, 'La cuenta tiene depósitos. Desactívala en lugar de eliminarla.');
        $cuenta->delete();

        return $this->ok('Cuenta bancaria eliminada.');
    }

    private function form(CuentaBancaria $cuenta): View
    {
        return view('caja.cuentas.form', ['cuenta' => $cuenta, 'empresas' => Empresa::activas()->pluck('nombre', 'id')]);
    }
}
