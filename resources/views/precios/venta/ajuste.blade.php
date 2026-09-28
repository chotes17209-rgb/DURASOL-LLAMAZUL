<x-form-modal title="Subir o bajar precios" subtitle="Aplica una variación a todos los clientes que tienen precio para el producto." icon="arrows-up-down"
              :action="route('precios.venta.ajuste.store')" confirm="¿Aplicar el ajuste de precio a los clientes?">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.select name="producto_id" label="Producto" :options="$productos" required/>
        <x-field.input name="variacion" type="number" step="0.01" label="Variación (S/)" required hint="Ej.: 0.70 para subir, -1.00 para bajar"/>
        <x-field.select name="chofer_id" label="Solo clientes del chofer" :options="$choferes" placeholder="— Todos los clientes —"/>
        <x-field.input name="vigente_desde" type="date" label="Vigente desde" :value="today()->format('Y-m-d')" required/>
        <x-field.input name="motivo" label="Motivo" placeholder="Ej.: variación de precio Solgas" class="sm:col-span-2"/>
    </div>
</x-form-modal>
