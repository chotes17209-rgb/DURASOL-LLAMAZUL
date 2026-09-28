<?php

namespace App\Http\Requests;

use App\Models\ParteFila;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParteRequest extends FormRequest
{
    public function rules(): array
    {
        $cantidad = ['nullable', 'integer', 'min:0', 'max:100000'];

        return [
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'filas' => ['array'],
            'filas.*.bloque' => ['required', Rule::in(ParteFila::BLOQUES)],
            'filas.*.placa' => ['nullable', 'string', 'max:30'],
            'filas.*.responsable' => ['nullable', 'string', 'max:60'],
            'filas.*.lugar' => ['nullable', 'string', 'max:40'],
            'filas.*.empresa_id' => ['nullable', 'exists:empresas,id'],
            'filas.*.instalacion_id' => ['nullable', 'exists:instalaciones,id'],
            'filas.*.numero_guia' => ['nullable', 'string', 'max:30'],
            'filas.*.s10' => $cantidad,
            'filas.*.s45' => $cantidad,
            'filas.*.m10' => $cantidad,
            'filas.*.cambio_s10' => $cantidad,
            'filas.*.cambio_s45' => $cantidad,
            'filas.*.cambio_m10' => $cantidad,
            'filas.*.color_s10' => $cantidad,
            'filas.*.color_s45' => $cantidad,
            'filas.*.observacion' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return ['filas.*.placa' => 'placa', 'filas.*.responsable' => 'responsable', 'filas.*.s10' => 'cantidad'];
    }

    /** Descarta las filas que no tienen ninguna cantidad. */
    public function filasConDatos(): array
    {
        $columnas = ['s10', 's45', 'm10', 'cambio_s10', 'cambio_s45', 'cambio_m10', 'color_s10', 'color_s45'];

        return array_values(array_filter($this->validated('filas', []), function ($fila) use ($columnas) {
            foreach ($columnas as $c) {
                if ((int) ($fila[$c] ?? 0) > 0) {
                    return true;
                }
            }

            return false;
        }));
    }
}
