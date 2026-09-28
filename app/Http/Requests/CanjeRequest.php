<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CanjeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'contraparte' => ['required', 'string', 'max:100'],
            'producto_id' => ['required', 'exists:productos,id'],
            'colores_entregados' => ['required', 'integer', 'min:0'],
            'plomos_recibidos' => ['required', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['contraparte' => 'con quién se hizo el canje', 'producto_id' => 'tipo de balón', 'colores_entregados' => 'colores entregados', 'plomos_recibidos' => 'plomos recibidos'];
    }
}
