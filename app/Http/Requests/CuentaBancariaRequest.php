<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CuentaBancariaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'banco' => ['required', 'string', 'max:40'],
            'alias' => ['required', 'string', 'max:60'],
            'numero' => ['nullable', 'string', 'max:40'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'moneda' => ['required', 'in:PEN,USD'],
            'activo' => ['boolean'],
        ];
    }
}
