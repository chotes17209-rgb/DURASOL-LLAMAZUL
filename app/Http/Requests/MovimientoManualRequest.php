<?php

namespace App\Http\Requests;

use App\Enums\EstadoStock;
use App\Enums\TipoMovimientoManual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimientoManualRequest extends FormRequest
{
    public function rules(): array
    {
        $conEmpresa = in_array($this->estado, [EstadoStock::Lleno->value, EstadoStock::Cambio->value], true);

        return [
            'fecha' => ['required', 'date'],
            'tipo' => ['required', Rule::enum(TipoMovimientoManual::class)],
            'sentido' => ['required', Rule::in(['entrada', 'salida'])],
            'estado' => ['required', Rule::enum(EstadoStock::class)],
            'empresa_id' => [$conEmpresa ? 'required' : 'nullable', 'exists:empresas,id'],
            'producto_id' => ['required', 'exists:productos,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['empresa_id.required' => 'Los balones llenos y los cambios pertenecen a una empresa: selecciónala.'];
    }

    public function attributes(): array
    {
        return ['estado' => 'estado del balón', 'producto_id' => 'producto'];
    }
}
