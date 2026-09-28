<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['placa' => mb_strtoupper(trim((string) $this->placa))]);
    }

    public function rules(): array
    {
        return [
            'placa' => ['required', 'string', 'max:12', Rule::unique('vehiculos')->ignore($this->route('vehiculo')?->id)],
            'tipo' => ['required', Rule::in(array_keys(Vehiculo::TIPOS))],
            'marca' => ['nullable', 'string', 'max:40'],
            'modelo' => ['nullable', 'string', 'max:40'],
            'anio' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:30'],
            'capacidad_balones' => ['nullable', 'integer', 'min:0'],
            'kilometraje' => ['nullable', 'integer', 'min:0'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'estado' => ['required', Rule::in(array_keys(Vehiculo::ESTADOS))],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['anio' => 'año', 'capacidad_balones' => 'capacidad', 'empresa_id' => 'empresa'];
    }
}
