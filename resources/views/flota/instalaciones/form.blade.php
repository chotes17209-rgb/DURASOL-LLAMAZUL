<x-form-modal :title="$instalacion->exists ? 'Editar instalación '.$instalacion->codigo : 'Nueva instalación'"
              :action="$instalacion->exists ? route('instalaciones.update', $instalacion) : route('instalaciones.store')" :method="$instalacion->exists ? 'PUT' : 'POST'">
    <p class="section-title">Datos de la instalación</p>
    <div class="grid gap-3 sm:grid-cols-3">
        <x-field.select name="empresa_id" label="Empresa" :options="$empresas" :selected="$instalacion->empresa_id" required/>
        <x-field.input name="codigo" label="Código Solgas (8 dígitos)" :value="$instalacion->codigo" required maxlength="8" inputmode="numeric" placeholder="62170831"/>
        <x-field.input name="planta" label="Planta" :value="$instalacion->planta" placeholder="P.HUA" list="lista-plantas"/>
        <x-field.input name="responsable" label="Responsable (como en el cuadro)" :value="$instalacion->responsable" placeholder="AGUILAR"/>
        <x-field.input name="placas" label="Placa(s)" :value="$instalacion->placas" placeholder="W6D-892, BRU-782" hint="Separa varias placas con coma."/>
        <x-field.input name="nombre" label="Nombre / referencia" :value="$instalacion->nombre" required/>
        <x-field.select name="chofer_id" label="Chofer designado" :options="$choferes" :selected="$instalacion->chofer_id" placeholder="— Sin chofer —"/>
        <x-field.select name="vehiculo_id" label="Camión principal" :options="$vehiculos" :selected="$instalacion->vehiculo_id" placeholder="— Sin camión —"/>
        <x-field.input name="direccion" label="Dirección" :value="$instalacion->direccion"/>
    </div>
    <datalist id="lista-plantas"><option value="P.HUA"><option value="P.LIMA"><option value="P.AYACUCHO"></datalist>

    <p class="section-title mt-5">Precio de compra</p>
    <div class="grid gap-3 sm:grid-cols-4">
        <x-field.input name="vigente_desde" type="date" label="Vigente desde" :value="today()->format('Y-m-d')"/>
        @foreach ($productos as $p)
            <x-field.input :name="'precios['.$p->id.']'" type="number" step="0.01" min="0" :label="$p->codigo.' (S/)'" :value="isset($vigentes[$p->id]) ? $vigentes[$p->id]->precio : null"/>
        @endforeach
    </div>
    <p class="form-hint">Si cambias un precio se guarda como un registro nuevo (queda el historial) y se marca como <b>no validado</b> hasta que figure en la factura.</p>

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <x-field.textarea name="observaciones" label="Observaciones" :value="$instalacion->observaciones" rows="2" class="sm:col-span-2"/>
        <x-field.toggle name="activo" label="Instalación activa" :checked="$instalacion->activo"/>
    </div>
</x-form-modal>
