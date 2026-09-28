<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreciosVentaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vigente_desde' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:200'],
            'precios' => ['required', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    public function attributes(): array
    {
        return ['vigente_desde' => 'vigente desde', 'precios.*' => 'precio'];
    }
}
