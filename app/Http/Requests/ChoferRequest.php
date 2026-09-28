<?php

namespace App\Http\Requests;

use App\Enums\TipoChofer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChoferRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['alias' => mb_strtoupper(trim((string) $this->alias))]);
    }

    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:40', Rule::unique('choferes')->ignore($this->route('chofer')?->id)],
            'nombre_completo' => ['nullable', 'string', 'max:150'],
            'dni' => ['nullable', 'digits_between:8,12'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'tipo' => ['required', Rule::enum(TipoChofer::class)],
            'licencia' => ['nullable', 'string', 'max:20'],
            'licencia_categoria' => ['nullable', 'string', 'max:10'],
            'licencia_vence' => ['nullable', 'date'],
            'vehiculo_id' => ['nullable', 'exists:vehiculos,id'],
            'activo' => ['boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['alias' => 'nombre corto', 'dni' => 'DNI', 'vehiculo_id' => 'vehículo', 'licencia_vence' => 'vencimiento de licencia'];
    }
}
