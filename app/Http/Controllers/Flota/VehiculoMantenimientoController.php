<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehiculoMantenimientoRequest;
use App\Models\Vehiculo;
use App\Models\VehiculoMantenimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class VehiculoMantenimientoController extends Controller
{
    public function create(Vehiculo $vehiculo): View
    {
        $mantenimiento = new VehiculoMantenimiento(['fecha' => today(), 'tipo' => 'preventivo', 'kilometraje' => $vehiculo->kilometraje]);

        return view('flota.mantenimientos.form', compact('mantenimiento', 'vehiculo'));
    }

    public function store(VehiculoMantenimientoRequest $request, Vehiculo $vehiculo): JsonResponse
    {
        $mantenimiento = $vehiculo->mantenimientos()->create($request->validated());
        if ($mantenimiento->kilometraje && $mantenimiento->kilometraje > (int) $vehiculo->kilometraje) {
            $vehiculo->update(['kilometraje' => $mantenimiento->kilometraje]);
        }

        return $this->ok('Mantenimiento registrado.', ['reloadModal' => true]);
    }

    public function show(VehiculoMantenimiento $mantenimiento): View
    {
        return view('flota.mantenimientos.show', compact('mantenimiento'));
    }

    public function edit(VehiculoMantenimiento $mantenimiento): View
    {
        return view('flota.mantenimientos.form', ['mantenimiento' => $mantenimiento, 'vehiculo' => $mantenimiento->vehiculo]);
    }

    public function update(VehiculoMantenimientoRequest $request, VehiculoMantenimiento $mantenimiento): JsonResponse
    {
        $mantenimiento->update($request->validated());

        return $this->ok('Mantenimiento actualizado.', ['reloadModal' => true]);
    }

    public function destroy(VehiculoMantenimiento $mantenimiento): JsonResponse
    {
        $mantenimiento->delete();

        return $this->ok('Mantenimiento eliminado.', ['reloadModal' => true]);
    }
}
