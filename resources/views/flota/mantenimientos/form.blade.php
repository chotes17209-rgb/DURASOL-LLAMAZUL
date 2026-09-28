<x-form-modal :title="($mantenimiento->exists ? 'Editar mantenimiento' : 'Registrar mantenimiento').' · '.$vehiculo->placa" icon="wrench-screwdriver"
              :action="$mantenimiento->exists ? route('mantenimientos.update', $mantenimiento) : route('mantenimientos.store', $vehiculo)" :method="$mantenimiento->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="fecha" type="date" label="Fecha" :value="$mantenimiento->fecha?->format('Y-m-d')" required/>
        <x-field.select name="tipo" label="Tipo" :options="\App\Models\VehiculoMantenimiento::TIPOS" :selected="$mantenimiento->tipo" :empty="false" required/>
        <x-field.input name="descripcion" label="Descripción del trabajo" :value="$mantenimiento->descripcion" required class="sm:col-span-2"/>
        <x-field.input name="taller" label="Taller" :value="$mantenimiento->taller"/>
        <x-field.input name="costo" type="number" step="0.01" min="0" label="Costo" prefix="S/" :value="$mantenimiento->costo ?? 0" required/>
        <x-field.input name="kilometraje" type="number" min="0" label="Kilometraje" :value="$mantenimiento->kilometraje"/>
        <div></div>
        <x-field.input name="proximo_fecha" type="date" label="Próximo mantenimiento (fecha)" :value="$mantenimiento->proximo_fecha?->format('Y-m-d')"/>
        <x-field.input name="proximo_kilometraje" type="number" min="0" label="Próximo mantenimiento (km)" :value="$mantenimiento->proximo_kilometraje"/>
    </div>
</x-form-modal>
