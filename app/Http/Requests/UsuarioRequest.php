<?php

namespace App\Http\Requests;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['username' => mb_strtolower(trim((string) $this->username))]);
    }

    public function rules(): array
    {
        $id = $this->route('usuario')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($id)],
            'email' => ['required', 'email', 'max:120', Rule::unique('users')->ignore($id)],
            'role' => ['required', Rule::enum(Rol::class)],
            'active' => ['boolean'],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'username' => 'usuario', 'role' => 'rol', 'password' => 'contraseña'];
    }
}
