<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'cuenta_bancaria_id' => ['required', 'exists:cuentas_bancarias,id'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'chofer_id' => ['nullable', 'exists:choferes,id'],
            'depositante' => ['nullable', 'string', 'max:100'],
            'numero_operacion' => ['nullable', 'string', 'max:40'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['cuenta_bancaria_id' => 'cuenta bancaria', 'chofer_id' => 'responsable', 'numero_operacion' => 'número de operación'];
    }
}
