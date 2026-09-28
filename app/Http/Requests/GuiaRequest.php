<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Salida a planta: la guía indica cuántos balones se compran y el camión lleva
 * la misma cantidad en vacíos (plomo o de color) o cambios.
 */
class GuiaRequest extends FormRequest
{
    public function rules(): array
    {
        $guia = $this->route('guia');

        return [
            'numero_guia' => ['required', 'string', 'max:30',
                Rule::unique('guias')->where('empresa_id', $this->empresa_id)->ignore($guia?->id)->whereNull('deleted_at')],
            'empresa_id' => ['required', 'exists:empresas,id'],
            'instalacion_id' => ['required', Rule::exists('instalaciones', 'id')->where('empresa_id', $this->empresa_id)],
            'vehiculo_id' => ['nullable', 'exists:vehiculos,id'],
            'chofer_id' => ['nullable', 'exists:choferes,id'],
            'fecha_salida' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'acepto_diferencia' => ['boolean'],
            'detalles' => ['required', 'array'],
            'detalles.*.producto_id' => ['required', Rule::exists('productos', 'id')],
            'detalles.*.cantidad_guia' => ['nullable', 'integer', 'min:0'],
            'detalles.*.precio_compra' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.vacios_enviados' => ['nullable', 'integer', 'min:0'],
            'detalles.*.colores_enviados' => ['nullable', 'integer', 'min:0'],
            'detalles.*.cambios_enviados' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $detalles = $this->detallesConDatos();
            if ($detalles === []) {
                $validator->errors()->add('detalles', 'Ingresa al menos un producto con cantidad en la guía.');

                return;
            }
            if ($this->boolean('acepto_diferencia')) {
                return;
            }
            foreach ($detalles as $d) {
                $enviados = (int) ($d['vacios_enviados'] ?? 0) + (int) ($d['colores_enviados'] ?? 0) + (int) ($d['cambios_enviados'] ?? 0);
                if ($enviados !== (int) ($d['cantidad_guia'] ?? 0)) {
                    $codigo = Producto::find($d['producto_id'])?->codigo;
                    $validator->errors()->add('detalles', "{$codigo}: la guía dice {$d['cantidad_guia']} balones y se envían {$enviados} (vacíos + colores + cambios). Marca «Aceptar diferencia» si es correcto.");
                }
            }
        }];
    }

    /** Solo las filas donde se ingresó algún número. */
    public function detallesConDatos(): array
    {
        return array_values(array_filter($this->input('detalles', []), fn ($d) => (int) ($d['cantidad_guia'] ?? 0) + (int) ($d['vacios_enviados'] ?? 0)
            + (int) ($d['colores_enviados'] ?? 0) + (int) ($d['cambios_enviados'] ?? 0) > 0));
    }

    public function attributes(): array
    {
        return ['numero_guia' => 'número de guía', 'empresa_id' => 'empresa', 'instalacion_id' => 'instalación', 'fecha_salida' => 'fecha de salida'];
    }

    public function messages(): array
    {
        return [
            'numero_guia.unique' => 'Ya existe una guía con ese número para la empresa.',
            'instalacion_id.exists' => 'La instalación no pertenece a la empresa seleccionada.',
        ];
    }
}
