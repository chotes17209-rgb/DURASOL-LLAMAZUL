<x-form-modal :title="$empresa->exists ? 'Editar empresa' : 'Nueva empresa'" icon="briefcase"
              :action="$empresa->exists ? route('empresas.update', $empresa) : route('empresas.store')" :method="$empresa->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="nombre" label="Nombre comercial" :value="$empresa->nombre" required maxlength="60"/>
        <x-field.input name="ruc" label="RUC" :value="$empresa->ruc" maxlength="11" inputmode="numeric"/>
        <x-field.input name="razon_social" label="Razón social" :value="$empresa->razon_social" class="sm:col-span-2"/>
        <x-field.input name="direccion" label="Dirección" :value="$empresa->direccion" class="sm:col-span-2"/>
        <x-field.input name="telefono" label="Teléfono" :value="$empresa->telefono"/>
        <x-field.input name="color" type="color" label="Color identificador" :value="$empresa->color" class="[&_input]:h-10 [&_input]:p-1"/>
        <x-field.toggle name="activo" label="Empresa activa" :checked="$empresa->activo"/>
    </div>
</x-form-modal>
