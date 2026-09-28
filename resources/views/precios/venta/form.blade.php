<x-form-modal :title="'Precios de '.$cliente->nombre" subtitle="Solo se guardan los precios que cambies." icon="tag" :action="route('precios.venta.update', $cliente)" method="PUT">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="vigente_desde" type="date" label="Vigente desde" :value="today()->format('Y-m-d')" required/>
        <x-field.input name="motivo" label="Motivo del cambio" placeholder="Ej.: subida de precio Solgas"/>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-4">
        @foreach ($productos as $p)
            <x-field.input :name="'precios['.$p->id.']'" type="number" step="0.01" min="0" :label="$p->codigo" prefix="S/" :value="$vigentes[$p->id] ?? null"
                           :hint="isset($vigentes[$p->id]) ? 'Actual: '.num($vigentes[$p->id], 2) : 'Sin precio'"/>
        @endforeach
    </div>
</x-form-modal>
