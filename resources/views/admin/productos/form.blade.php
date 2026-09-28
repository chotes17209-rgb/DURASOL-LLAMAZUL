<x-form-modal :title="$producto->exists ? 'Editar producto' : 'Nuevo producto'" icon="fire"
              :action="$producto->exists ? route('productos.update', $producto) : route('productos.store')" :method="$producto->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="codigo" label="Código" :value="$producto->codigo" required maxlength="10" hint="Ej.: S10, S45, M10"/>
        <x-field.input name="nombre" label="Nombre" :value="$producto->nombre" required/>
        <x-field.input name="marca" label="Marca" :value="$producto->marca"/>
        <x-field.select name="tipo" label="Tipo" :options="['gas' => 'Balón con gas', 'envase' => 'Envase vacío (venta de balón)', 'accesorio' => 'Accesorio']" :selected="$producto->tipo" :empty="false" required/>
        <x-field.input name="capacidad_kg" type="number" label="Capacidad (kg)" :value="$producto->capacidad_kg" min="1"/>
        <x-field.select name="envase_id" label="Envase vacío que genera" :options="$envases" :selected="$producto->envase_id" placeholder="— Ninguno —"/>
        <x-field.input name="orden" type="number" label="Orden en pantallas" :value="$producto->orden ?? 0" min="0"/>
        <x-field.input name="costo_referencial" type="number" step="0.01" min="0" label="Costo referencial (S/)" prefix="S/" :value="$producto->costo_referencial"
                       hint="Para rentabilidad de lo que no se compra en planta (Contigas, envases, reguladores)."/>
        <x-field.toggle name="se_compra_en_planta" label="Se compra en planta Solgas" :checked="$producto->se_compra_en_planta"/>
        <x-field.toggle name="controla_stock" label="Controla stock" :checked="$producto->controla_stock"/>
        <x-field.toggle name="activo" label="Producto activo" :checked="$producto->activo"/>
    </div>
</x-form-modal>
