<?php

namespace App\Http\Requests;

use App\Enums\CategoriaCaja;
use App\Models\CajaMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CajaMovimientoRequest extends FormRequest
{
    public function rules(): array
    {
        // Liquidaciones, cobranzas y depósitos se registran desde sus propios módulos.
        $categorias = [CategoriaCaja::Gasto, CategoriaCaja::CajaChica, CategoriaCaja::Planilla, CategoriaCaja::Otro];

        return [
            'fecha' => ['required', 'date'],
            'tipo' => ['required', Rule::in([CajaMovimiento::INGRESO, CajaMovimiento::EGRESO])],
            'categoria' => ['required', Rule::in(array_map(fn ($c) => $c->value, $categorias))],
            'monto' => ['required', 'numeric', 'gt:0'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'descripcion' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['categoria' => 'categoría', 'descripcion' => 'descripción'];
    }
}
