<?php

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CobranzaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'numero_operacion' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function attributes(): array
    {
        return ['cliente_id' => 'cliente', 'metodo_pago' => 'método de pago'];
    }
}
