<x-form-modal :title="'Editar precio de compra · '.$precio->producto?->codigo" :subtitle="$precio->instalacion?->codigo.' · '.($precio->instalacion?->responsable ?? $precio->instalacion?->nombre).' · '.$precio->instalacion?->empresa?->nombre"
              :action="route('precios.compra.update', $precio)" method="PUT">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="precio" type="number" step="0.01" min="0.01" label="Precio por balón" prefix="S/" :value="$precio->precio" required/>
        <x-field.input name="vigente_desde" type="date" label="Vigente desde" :value="$precio->vigente_desde?->format('Y-m-d')" required/>
        <x-field.input name="motivo" label="Motivo" :value="$precio->motivo" class="sm:col-span-2"/>
        <div class="sm:col-span-2"><x-field.toggle name="validado" label="Validado con la factura de Solgas" :checked="$precio->validado"/></div>
    </div>
    <p class="form-hint mt-3">La corrección cambia el costo de las ventas que se registren desde ahora; las ventas ya registradas conservan su costo.</p>
</x-form-modal>
