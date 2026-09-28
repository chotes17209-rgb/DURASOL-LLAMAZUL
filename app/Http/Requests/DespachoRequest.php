<?php

namespace App\Http\Requests;

use App\Enums\TipoChofer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DespachoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'chofer_id' => ['required', 'exists:choferes,id'],
            'vehiculo_id' => ['nullable', 'exists:vehiculos,id'],
            'vuelta' => ['required', 'integer', 'min:1', 'max:20'],
            'tipo' => ['required', Rule::enum(TipoChofer::class)],
            'destino' => ['nullable', 'string', 'max:100'],
            'hora_salida' => ['nullable', 'date_format:H:i'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array'],
            'detalles.*.*.llenos_salida' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->lineas() === []) {
                $validator->errors()->add('detalles', 'Ingresa la cantidad de balones que salen.');
            }
        }];
    }

    /** @return array<int, array{empresa_id: int, producto_id: int, llenos_salida: int}> */
    public function lineas(): array
    {
        $lineas = [];
        foreach ($this->input('detalles', []) as $empresaId => $productos) {
            foreach ($productos as $productoId => $valores) {
                $salida = (int) ($valores['llenos_salida'] ?? 0);
                if ($salida > 0) {
                    $lineas[] = ['empresa_id' => (int) $empresaId, 'producto_id' => (int) $productoId, 'llenos_salida' => $salida];
                }
            }
        }

        return $lineas;
    }

    public function attributes(): array
    {
        return ['chofer_id' => 'chofer', 'vehiculo_id' => 'vehículo', 'hora_salida' => 'hora de salida'];
    }
}
