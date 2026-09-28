<?php

namespace App\Http\Controllers\Flota;

use App\Enums\TipoDocumentoVehicular;
use App\Http\Controllers\Controller;
use App\Http\Requests\VehiculoDocumentoRequest;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehiculoDocumentoController extends Controller
{
    /** Todos los documentos de la flota con filtro por vencimiento. */
    public function index(Request $request)
    {
        $documentos = VehiculoDocumento::with('vehiculo')
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->vehiculo_id, fn ($q, $v) => $q->where('vehiculo_id', $v))
            ->when($request->alerta === 'vencidos', fn ($q) => $q->whereDate('fecha_vencimiento', '<', today()))
            ->when($request->alerta === 'por_vencer', fn ($q) => $q->whereBetween('fecha_vencimiento', [today()->toDateString(), today()->addDays(VehiculoDocumento::DIAS_ALERTA)->toDateString()]))
            ->orderBy('fecha_vencimiento')->paginate(30)->withQueryString();

        $vehiculos = Vehiculo::orderBy('placa')->pluck('placa', 'id');

        return $this->tableOrPage($request, 'flota.documentos.index', 'flota.documentos._table', compact('documentos', 'vehiculos'));
    }

    public function create(Request $request, Vehiculo $vehiculo): View
    {
        $documento = new VehiculoDocumento(['vehiculo_id' => $vehiculo->id, 'tipo' => $request->tipo ?? TipoDocumentoVehicular::Soat->value]);

        return view('flota.documentos.form', compact('documento', 'vehiculo'));
    }

    public function store(VehiculoDocumentoRequest $request, Vehiculo $vehiculo): JsonResponse
    {
        $data = $request->safe()->except('archivo');
        if ($request->hasFile('archivo')) {
            $data['archivo'] = $request->file('archivo')->store('documentos-vehiculares');
        }
        $vehiculo->documentos()->create($data);

        return $this->ok('Documento registrado.', ['reloadModal' => true]);
    }

    public function show(VehiculoDocumento $documento): View
    {
        return view('flota.documentos.show', compact('documento'));
    }

    public function edit(VehiculoDocumento $documento): View
    {
        return view('flota.documentos.form', ['documento' => $documento, 'vehiculo' => $documento->vehiculo]);
    }

    public function update(VehiculoDocumentoRequest $request, VehiculoDocumento $documento): JsonResponse
    {
        $data = $request->safe()->except('archivo');
        if ($request->hasFile('archivo')) {
            if ($documento->archivo) {
                Storage::delete($documento->archivo);
            }
            $data['archivo'] = $request->file('archivo')->store('documentos-vehiculares');
        }
        $documento->update($data);

        return $this->ok('Documento actualizado.', ['reloadModal' => true]);
    }

    public function destroy(VehiculoDocumento $documento): JsonResponse
    {
        $documento->delete();

        return $this->ok('Documento eliminado.', ['reloadModal' => true]);
    }

    public function archivo(VehiculoDocumento $documento): StreamedResponse
    {
        abort_unless($documento->archivo && Storage::exists($documento->archivo), 404);

        return Storage::response($documento->archivo);
    }
}
