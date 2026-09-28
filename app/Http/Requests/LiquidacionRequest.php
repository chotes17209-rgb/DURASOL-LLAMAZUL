<?php

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use App\Enums\TipoChofer;
use App\Models\LiquidacionFise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LiquidacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Normalmente se liquida al día siguiente; también se permite el mismo día o después (rutas largas).
            'fecha_venta' => ['required', 'date', 'before_or_equal:today'],
            'fecha_liquidacion' => ['required', 'date', 'after_or_equal:fecha_venta', 'before_or_equal:today'],
            'chofer_id' => ['required', 'exists:choferes,id'],
            'vehiculo_id' => ['nullable', 'exists:vehiculos,id'],
            'tipo' => ['required', Rule::enum(TipoChofer::class)],
            'efectivo_entregado' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:2000'],

            'items' => ['array'],
            'items.*.cliente_id' => ['required', 'exists:clientes,id'],
            'items.*.empresa_id' => ['required', 'exists:empresas,id'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.precio' => ['required', 'numeric', 'min:0', 'max:99999'],
            'items.*.vacios_devueltos' => ['nullable', 'integer', 'min:0'],
            'items.*.metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'items.*.es_credito' => ['boolean'],
            'items.*.monto_credito' => ['nullable', 'numeric', 'min:0'],
            'items.*.numero_operacion' => ['nullable', 'string', 'max:40'],
            'items.*.observacion' => ['nullable', 'string', 'max:200'],

            'fises' => ['array'],
            'fises.*.cliente_id' => ['nullable', 'exists:clientes,id'],
            'fises.*.valor' => ['required', Rule::in(LiquidacionFise::VALORES)],
            'fises.*.cantidad' => ['required', 'integer', 'min:0', 'max:10000'],

            'cobranzas' => ['array'],
            'cobranzas.*.cliente_id' => ['required', 'exists:clientes,id'],
            'cobranzas.*.monto' => ['required', 'numeric', 'gt:0'],
            'cobranzas.*.metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'cobranzas.*.numero_operacion' => ['nullable', 'string', 'max:40'],

            'gastos' => ['array'],
            'gastos.*.concepto' => ['required', 'string', 'max:150'],
            'gastos.*.monto' => ['required', 'numeric', 'gt:0'],
            'gastos.*.comprobante' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('items', []) as $i => $item) {
                if (! empty($item['es_credito']) && (float) ($item['monto_credito'] ?? 0) > (int) $item['cantidad'] * (float) $item['precio'] + 0.001) {
                    $validator->errors()->add("items.$i.monto_credito", 'El crédito no puede ser mayor al total de la venta.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'fecha_venta.before_or_equal' => 'La fecha de venta no puede ser futura.',
            'fecha_liquidacion.after_or_equal' => 'La fecha de liquidación no puede ser anterior a la fecha de venta.',
            'fecha_liquidacion.before_or_equal' => 'La fecha de liquidación no puede ser futura.',
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_venta' => 'fecha de venta',
            'fecha_liquidacion' => 'fecha de liquidación',
            'chofer_id' => 'chofer',
            'items.*.cliente_id' => 'cliente',
            'items.*.cantidad' => 'cantidad',
            'items.*.precio' => 'precio',
            'items.*.metodo_pago' => 'método de pago',
            'cobranzas.*.monto' => 'monto de cobranza',
            'gastos.*.concepto' => 'concepto del gasto',
            'gastos.*.monto' => 'monto del gasto',
        ];
    }
}
