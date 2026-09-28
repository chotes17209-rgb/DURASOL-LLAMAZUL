<?php

namespace App\Http\Requests;

use App\Models\Guia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Retorno de planta. Control de masa: todo lo que salió debe volver
 * (como lleno comprado, reposición de cambio o vacío rechazado).
 */
class RecepcionGuiaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fecha_recepcion' => ['required', 'date'],
            'detalles' => ['required', 'array'],
            'detalles.*.llenos_recibidos' => ['nullable', 'integer', 'min:0'],
            'detalles.*.cambios_repuestos' => ['nullable', 'integer', 'min:0'],
            'detalles.*.vacios_rechazados' => ['nullable', 'integer', 'min:0'],
            'detalles.*.colores_rechazados' => ['nullable', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Guia $guia */
            $guia = $this->route('guia');
            if ($this->date('fecha_recepcion')?->lt($guia->fecha_salida)) {
                $validator->errors()->add('fecha_recepcion', 'La recepción no puede ser anterior a la salida.');
            }
            foreach ($guia->detalles as $d) {
                $input = $this->input("detalles.{$d->id}", []);
                $retorno = (int) ($input['llenos_recibidos'] ?? 0) + (int) ($input['cambios_repuestos'] ?? 0)
                    + (int) ($input['vacios_rechazados'] ?? 0) + (int) ($input['colores_rechazados'] ?? 0);
                if ($retorno !== $d->totalEnviado()) {
                    $validator->errors()->add('detalles', "{$d->producto->codigo}: salieron {$d->totalEnviado()} balones y regresan {$retorno}. El movimiento de masa debe cuadrar.");
                }
                if ((int) ($input['cambios_repuestos'] ?? 0) > $d->cambios_enviados) {
                    $validator->errors()->add('detalles', "{$d->producto->codigo}: no pueden reponerse más cambios de los que se enviaron ({$d->cambios_enviados}).");
                }
            }
        }];
    }

    public function attributes(): array
    {
        return ['fecha_recepcion' => 'fecha de recepción'];
    }
}
