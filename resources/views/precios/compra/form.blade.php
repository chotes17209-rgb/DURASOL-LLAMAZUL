<x-form-modal :title="'Precios de compra · '.$instalacion->codigo" :subtitle="$instalacion->empresa?->nombre.' · '.$instalacion->nombre" icon="building-storefront"
              :action="route('precios.compra.store')">
    <input type="hidden" name="instalacion_id" value="{{ $instalacion->id }}">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="vigente_desde" type="date" label="Vigente desde" :value="today()->format('Y-m-d')" required/>
        <x-field.input name="motivo" label="Motivo" placeholder="Ej.: nueva lista de precios Solgas"/>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-4">
        @foreach ($productos as $p)
            <x-field.input :name="'precios['.$p->id.']'" type="number" step="0.01" min="0" :label="$p->codigo" prefix="S/" :value="isset($vigentes[$p->id]) ? $vigentes[$p->id]->precio : null"
                           :hint="isset($vigentes[$p->id]) ? 'Actual: '.num($vigentes[$p->id]->precio, 2) : 'Sin precio'"/>
        @endforeach
    </div>
</x-form-modal>
