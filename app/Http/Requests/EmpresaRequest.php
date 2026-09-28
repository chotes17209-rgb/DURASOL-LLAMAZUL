<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmpresaRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('empresa')?->id;

        return [
            'nombre' => ['required', 'string', 'max:60', Rule::unique('empresas')->ignore($id)],
            'razon_social' => ['nullable', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits:11', Rule::unique('empresas')->ignore($id)],
            'direccion' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'activo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['razon_social' => 'razón social', 'ruc' => 'RUC', 'direccion' => 'dirección', 'telefono' => 'teléfono'];
    }
}
