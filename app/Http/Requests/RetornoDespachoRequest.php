<?php

namespace App\Http\Requests;

use App\Models\Despacho;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RetornoDespachoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'hora_retorno' => ['nullable', 'date_format:H:i'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array'],
            'detalles.*.llenos_retorno' => ['nullable', 'integer', 'min:0'],
            'detalles.*.vacios_retorno' => ['nullable', 'integer', 'min:0'],
            'detalles.*.colores_retorno' => ['nullable', 'integer', 'min:0'],
            'detalles.*.cambios_retorno' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Despacho $despacho */
            $despacho = $this->route('despacho');
            foreach ($despacho->detalles as $d) {
                $input = $this->input("detalles.{$d->id}", []);
                $noVendidos = (int) ($input['llenos_retorno'] ?? 0) + (int) ($input['cambios_retorno'] ?? 0);
                if ($noVendidos > $d->llenos_salida) {
                    $validator->errors()->add('detalles', "{$d->producto->codigo} {$d->empresa->nombre}: regresan {$noVendidos} llenos/cambios pero solo salieron {$d->llenos_salida}.");
                }
            }
        }];
    }
}
