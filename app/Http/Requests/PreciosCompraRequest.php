<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreciosCompraRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'instalacion_id' => ['required', 'exists:instalaciones,id'],
            'vigente_desde' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:200'],
            'precios' => ['required', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    public function attributes(): array
    {
        return ['instalacion_id' => 'instalación', 'vigente_desde' => 'vigente desde', 'precios.*' => 'precio'];
    }
}
