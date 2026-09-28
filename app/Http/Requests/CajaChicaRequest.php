<?php

namespace App\Http\Requests;

use App\Models\CajaChicaMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CajaChicaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'tipo' => ['required', Rule::in([CajaChicaMovimiento::APERTURA, CajaChicaMovimiento::REPOSICION, CajaChicaMovimiento::GASTO])],
            'concepto' => ['required', 'string', 'max:60'],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'comprobante' => ['nullable', 'string', 'max:40'],
            'proveedor' => ['nullable', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits_between:8,11'],
            'vehiculo_id' => ['nullable', 'exists:vehiculos,id'],
            'chofer_id' => ['nullable', 'exists:choferes,id'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['descripcion' => 'descripción', 'ruc' => 'RUC', 'vehiculo_id' => 'vehículo', 'chofer_id' => 'conductor', 'observacion' => 'observación'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'proveedor' => $this->proveedor ? mb_strtoupper(trim($this->proveedor)) : null,
            'descripcion' => $this->descripcion ? mb_strtoupper(trim($this->descripcion)) : null,
        ]);
    }
}
