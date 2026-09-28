<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AjustePrecioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'exists:productos,id'],
            'variacion' => ['required', 'numeric', 'not_in:0', 'between:-500,500'],
            'chofer_id' => ['nullable', 'exists:choferes,id'],
            'vigente_desde' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return ['producto_id' => 'producto', 'variacion' => 'variación', 'chofer_id' => 'chofer'];
    }
}
