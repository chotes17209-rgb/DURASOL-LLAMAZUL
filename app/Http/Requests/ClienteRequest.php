<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => mb_strtoupper(trim((string) $this->nombre)),
            'conocido_como' => $this->conocido_como ? mb_strtoupper(trim($this->conocido_como)) : null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('cliente')?->id;

        return [
            'codigo' => ['nullable', 'integer', 'min:1', Rule::unique('clientes')->ignore($id)],
            'nombre' => ['required', 'string', 'max:150'],
            'conocido_como' => ['nullable', 'string', 'max:100'],
            'documento' => ['nullable', 'regex:/^(\d{8}|\d{11})$/'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'zona' => ['nullable', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:120'],
            'chofer_id' => ['nullable', 'exists:choferes,id'],
            'tipo' => ['required', Rule::in(array_keys(Cliente::TIPOS))],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'precios' => ['nullable', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return ['documento.regex' => 'El documento debe ser un DNI (8 dígitos) o RUC (11 dígitos).'];
    }

    public function attributes(): array
    {
        return ['codigo' => 'código', 'chofer_id' => 'chofer responsable', 'limite_credito' => 'límite de crédito', 'precios.*' => 'precio'];
    }
}
