<x-form-modal :title="$movimiento->exists ? 'Editar movimiento de caja' : ($movimiento->tipo === 'ingreso' ? 'Nuevo ingreso a caja' : 'Nuevo egreso de caja')" icon="banknotes"
              :action="$movimiento->exists ? route('caja.movimientos.update', $movimiento) : route('caja.movimientos.store')" :method="$movimiento->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="fecha" type="date" label="Fecha" :value="$movimiento->fecha?->format('Y-m-d')" required/>
        <x-field.select name="tipo" label="Tipo" :options="['ingreso' => 'Ingreso', 'egreso' => 'Egreso']" :selected="$movimiento->tipo" :empty="false" required/>
        <x-field.select name="categoria" label="Categoría" :options="$categorias" :selected="$movimiento->categoria" :empty="false" required/>
        <x-field.input name="monto" type="number" step="0.01" min="0.01" label="Monto" prefix="S/" :value="$movimiento->monto" required/>
        <x-field.select name="empresa_id" label="Empresa" :options="$empresas" :selected="$movimiento->empresa_id" placeholder="— General —"/>
        <x-field.input name="descripcion" label="Descripción" :value="$movimiento->descripcion" required class="sm:col-span-2" placeholder="Ej.: pago de personal, combustible, caja chica..."/>
    </div>
</x-form-modal>
