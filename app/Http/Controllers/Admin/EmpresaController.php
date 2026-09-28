<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmpresaRequest;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function index(Request $request)
    {
        $empresas = Empresa::withCount('instalaciones')
            ->when($request->q, fn ($q, $t) => $q->where('nombre', 'like', "%$t%")->orWhere('ruc', 'like', "%$t%"))
            ->orderBy('nombre')->paginate(20)->withQueryString();

        return $this->tableOrPage($request, 'admin.empresas.index', 'admin.empresas._table', compact('empresas'));
    }

    public function create(): View
    {
        return view('admin.empresas.form', ['empresa' => new Empresa(['activo' => true, 'color' => '#2545ea'])]);
    }

    public function store(EmpresaRequest $request): JsonResponse
    {
        $empresa = Empresa::create($request->validated());

        return $this->ok("Empresa {$empresa->nombre} registrada.");
    }

    public function show(Empresa $empresa): View
    {
        $empresa->load('instalaciones.chofer', 'instalaciones.vehiculo');

        return view('admin.empresas.show', compact('empresa'));
    }

    public function edit(Empresa $empresa): View
    {
        return view('admin.empresas.form', compact('empresa'));
    }

    public function update(EmpresaRequest $request, Empresa $empresa): JsonResponse
    {
        $empresa->update($request->validated());

        return $this->ok("Empresa {$empresa->nombre} actualizada.");
    }

    public function destroy(Empresa $empresa): JsonResponse
    {
        if ($empresa->instalaciones()->exists()) {
            abort(422, 'La empresa tiene instalaciones registradas. Desactívala en lugar de eliminarla.');
        }
        $empresa->delete();

        return $this->ok('Empresa eliminada.');
    }
}
