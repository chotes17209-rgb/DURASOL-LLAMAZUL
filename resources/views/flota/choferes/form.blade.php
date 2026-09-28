<x-form-modal :title="$chofer->exists ? 'Editar chofer '.$chofer->alias : 'Nuevo chofer'" icon="identification"
              :action="$chofer->exists ? route('choferes.update', $chofer) : route('choferes.store')" :method="$chofer->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="alias" label="Nombre corto (como aparece en la liquidación)" :value="$chofer->alias" required maxlength="40" placeholder="URBANO"/>
        <x-field.select name="tipo" label="Tipo" :options="\App\Enums\TipoChofer::options()" :selected="$chofer->tipo" :empty="false" required/>
        <x-field.input name="nombre_completo" label="Nombre completo" :value="$chofer->nombre_completo" class="sm:col-span-2"/>
        <x-field.input name="dni" label="DNI" :value="$chofer->dni" inputmode="numeric" maxlength="12"/>
        <x-field.input name="telefono" label="Teléfono" :value="$chofer->telefono"/>
        <x-field.input name="licencia" label="N° de licencia" :value="$chofer->licencia"/>
        <x-field.input name="licencia_categoria" label="Categoría" :value="$chofer->licencia_categoria" placeholder="A-IIb"/>
        <x-field.input name="licencia_vence" type="date" label="Vencimiento de licencia" :value="$chofer->licencia_vence?->format('Y-m-d')"/>
        <x-field.select name="vehiculo_id" label="Vehículo asignado" :options="$vehiculos" :selected="$chofer->vehiculo_id" placeholder="— Sin vehículo —" tom/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$chofer->observaciones" rows="2" class="sm:col-span-2"/>
        <x-field.toggle name="activo" label="Chofer activo" :checked="$chofer->activo"/>
    </div>
</x-form-modal>
