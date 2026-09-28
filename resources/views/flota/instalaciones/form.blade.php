<x-form-modal :title="$instalacion->exists ? 'Editar instalación' : 'Nueva instalación'" icon="map-pin"
              :action="$instalacion->exists ? route('instalaciones.update', $instalacion) : route('instalaciones.store')" :method="$instalacion->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="codigo" label="Código de instalación (8 dígitos)" :value="$instalacion->codigo" required maxlength="8" inputmode="numeric" placeholder="12345678"/>
        <x-field.select name="empresa_id" label="Empresa" :options="$empresas" :selected="$instalacion->empresa_id" required/>
        <x-field.input name="nombre" label="Nombre / referencia" :value="$instalacion->nombre" required class="sm:col-span-2"/>
        <x-field.input name="direccion" label="Dirección" :value="$instalacion->direccion" class="sm:col-span-2"/>
        <x-field.select name="chofer_id" label="Chofer designado" :options="$choferes" :selected="$instalacion->chofer_id" placeholder="— Sin chofer —" tom/>
        <x-field.select name="vehiculo_id" label="Camión designado" :options="$vehiculos" :selected="$instalacion->vehiculo_id" placeholder="— Sin camión —" tom/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$instalacion->observaciones" rows="2" class="sm:col-span-2"/>
        <x-field.toggle name="activo" label="Instalación activa" :checked="$instalacion->activo"/>
    </div>
</x-form-modal>
