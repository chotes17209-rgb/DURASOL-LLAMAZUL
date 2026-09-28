<x-form-modal :title="$canje->exists ? 'Editar canje' : 'Nuevo canje'" icon="arrows-right-left"
              :action="$canje->exists ? route('logistica.canjes.update', $canje) : route('logistica.canjes.store')" :method="$canje->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="fecha" type="date" label="Fecha" :value="$canje->fecha?->format('Y-m-d')" required/>
        <x-field.select name="producto_id" label="Tipo de balón" :options="$envases" :selected="$canje->producto_id" :empty="false" required/>
        <x-field.input name="contraparte" label="Con quién se hizo el canje" :value="$canje->contraparte" required placeholder="Móvil Chino, Planta móvil..." class="sm:col-span-2"/>
        <x-field.input name="colores_entregados" type="number" min="0" label="Vacíos de color entregados" :value="$canje->colores_entregados ?? 0" required/>
        <x-field.input name="plomos_recibidos" type="number" min="0" label="Vacíos plomo recibidos" :value="$canje->plomos_recibidos ?? 0" required/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$canje->observaciones" rows="2" class="sm:col-span-2"/>
    </div>
</x-form-modal>
