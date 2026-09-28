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
            'planta' => ['nullable', 'string', 'max:40'],
            'responsable' => ['nullable', 'string', 'max:60'],
            'placas' => ['nullable', 'string', 'max:80'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'vigente_desde' => ['nullable', 'date'],
            'precios' => ['nullable', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'chofer_id' => ['nullable', 'exists:choferes,id'],
            'vehiculo_id' => ['nullable', 'exists:vehiculos,id'],
            'activo' => ['boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'planta' => $this->planta ? mb_strtoupper(trim($this->planta)) : null,
            'responsable' => $this->responsable ? mb_strtoupper(trim($this->responsable)) : null,
            'placas' => $this->placas ? mb_strtoupper(trim($this->placas)) : null,
        ]);
    }

    public function messages(): array
    {
        return ['codigo.digits' => 'El código de instalación debe tener exactamente 8 dígitos.'];
    }

    public function attributes(): array
    {
        return ['precios.*' => 'precio', 'codigo' => 'código', 'empresa_id' => 'empresa', 'chofer_id' => 'chofer', 'vehiculo_id' => 'camión'];
    }
}
