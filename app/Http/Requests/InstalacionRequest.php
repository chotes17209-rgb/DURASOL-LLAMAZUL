<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstalacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'digits:8', Rule::unique('instalaciones')->ignore($this->route('instalacion')?->id)],
            'nombre' => ['required', 'string', 'max:100'],
            'empresa_id' => ['required', 'exists:empresas,id'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'chofer_id' => ['nullable', 'exists:choferes,id'],
            'vehiculo_id' => ['nullable', 'exists:vehiculos,id'],
            'activo' => ['boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['codigo.digits' => 'El código de instalación debe tener exactamente 8 dígitos.'];
    }

    public function attributes(): array
    {
        return ['codigo' => 'código', 'empresa_id' => 'empresa', 'chofer_id' => 'chofer', 'vehiculo_id' => 'camión'];
    }
}
