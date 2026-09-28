<?php

namespace App\Http\Requests;

use App\Models\VehiculoMantenimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoMantenimientoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'tipo' => ['required', Rule::in(array_keys(VehiculoMantenimiento::TIPOS))],
            'kilometraje' => ['nullable', 'integer', 'min:0'],
            'descripcion' => ['required', 'string', 'max:255'],
            'taller' => ['nullable', 'string', 'max:100'],
            'costo' => ['required', 'numeric', 'min:0'],
            'proximo_fecha' => ['nullable', 'date', 'after_or_equal:fecha'],
            'proximo_kilometraje' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return ['descripcion' => 'descripción', 'proximo_fecha' => 'próximo mantenimiento'];
    }
}
