<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['codigo' => mb_strtoupper(trim((string) $this->codigo))]);
    }

    public function rules(): array
    {
        $id = $this->route('producto')?->id;

        return [
            'codigo' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('productos')->ignore($id)],
            'nombre' => ['required', 'string', 'max:80'],
            'marca' => ['nullable', 'string', 'max:40'],
            'capacidad_kg' => ['nullable', 'integer', 'min:1', 'max:100'],
            'envase_id' => ['nullable', 'exists:productos,id'],
            'tipo' => ['required', Rule::in([Producto::TIPO_GAS, Producto::TIPO_ENVASE, Producto::TIPO_ACCESORIO])],
            'se_compra_en_planta' => ['boolean'],
            'controla_stock' => ['boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['codigo' => 'código', 'capacidad_kg' => 'capacidad', 'envase_id' => 'envase'];
    }
}
