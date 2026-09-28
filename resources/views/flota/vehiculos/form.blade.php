<x-form-modal :title="$vehiculo->exists ? 'Editar vehículo '.$vehiculo->placa : 'Nuevo vehículo'" icon="truck"
              :action="$vehiculo->exists ? route('vehiculos.update', $vehiculo) : route('vehiculos.store')" :method="$vehiculo->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field.input name="placa" label="Placa" :value="$vehiculo->placa" required maxlength="12" placeholder="ABC-123"/>
        <x-field.select name="tipo" label="Tipo" :options="\App\Models\Vehiculo::TIPOS" :selected="$vehiculo->tipo" :empty="false" required/>
        <x-field.select name="estado" label="Estado" :options="\App\Models\Vehiculo::ESTADOS" :selected="$vehiculo->estado" :empty="false" required/>
        <x-field.input name="marca" label="Marca" :value="$vehiculo->marca"/>
        <x-field.input name="modelo" label="Modelo" :value="$vehiculo->modelo"/>
        <x-field.input name="anio" type="number" label="Año" :value="$vehiculo->anio" min="1980"/>
        <x-field.input name="color" label="Color" :value="$vehiculo->color"/>
        <x-field.input name="capacidad_balones" type="number" label="Capacidad (balones)" :value="$vehiculo->capacidad_balones" min="0"/>
        <x-field.input name="kilometraje" type="number" label="Kilometraje actual" :value="$vehiculo->kilometraje" min="0"/>
        <x-field.select name="empresa_id" label="Empresa propietaria" :options="$empresas" :selected="$vehiculo->empresa_id" placeholder="— Sin asignar —" class="sm:col-span-3"/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$vehiculo->observaciones" class="sm:col-span-3" rows="2"/>
    </div>
</x-form-modal>
