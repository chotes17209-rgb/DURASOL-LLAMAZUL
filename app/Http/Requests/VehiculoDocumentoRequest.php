<?php

namespace App\Http\Requests;

use App\Enums\TipoDocumentoVehicular;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoDocumentoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoDocumentoVehicular::class)],
            'numero' => ['nullable', 'string', 'max:60'],
            'entidad' => ['nullable', 'string', 'max:100'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['numero' => 'número', 'fecha_emision' => 'fecha de emisión', 'fecha_vencimiento' => 'fecha de vencimiento'];
    }
}
