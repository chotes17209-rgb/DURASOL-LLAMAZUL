<x-form-modal :title="$usuario->exists ? 'Editar usuario' : 'Nuevo usuario'" icon="user"
              :action="$usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store')" :method="$usuario->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="name" label="Nombre completo" :value="$usuario->name" required class="sm:col-span-2"/>
        <x-field.input name="username" label="Usuario" :value="$usuario->username" required/>
        <x-field.input name="email" type="email" label="Correo" :value="$usuario->email" required/>
        <x-field.select name="role" label="Rol" :options="\App\Enums\Rol::options()" :selected="$usuario->role" required class="sm:col-span-2"
                        hint="Administrador ve todo. Logística: stock, guías, despachos y flota. Caja: caja, depósitos y cobranzas. Liquidaciones: ventas, clientes y precios."/>
        <x-field.input name="password" type="password" label="Contraseña" :required="! $usuario->exists" :hint="$usuario->exists ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres.'" autocomplete="new-password"/>
        <x-field.input name="password_confirmation" type="password" label="Repetir contraseña" autocomplete="new-password"/>
        <x-field.toggle name="active" label="Usuario activo" :checked="$usuario->active"/>
    </div>
</x-form-modal>
