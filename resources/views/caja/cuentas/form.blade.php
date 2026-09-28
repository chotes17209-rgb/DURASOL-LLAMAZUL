<x-form-modal :title="$cuenta->exists ? 'Editar cuenta bancaria' : 'Nueva cuenta bancaria'" icon="building-library"
              :action="$cuenta->exists ? route('cuentas-bancarias.update', $cuenta) : route('cuentas-bancarias.store')" :method="$cuenta->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="banco" label="Banco / billetera" :value="$cuenta->banco" required placeholder="BCP, BBVA, Yape..."/>
        <x-field.input name="alias" label="Alias" :value="$cuenta->alias" required/>
        <x-field.input name="numero" label="Número de cuenta" :value="$cuenta->numero"/>
        <x-field.select name="empresa_id" label="Empresa titular" :options="$empresas" :selected="$cuenta->empresa_id" placeholder="— Otra —"/>
        <x-field.select name="moneda" label="Moneda" :options="['PEN' => 'Soles', 'USD' => 'Dólares']" :selected="$cuenta->moneda" :empty="false"/>
        <x-field.toggle name="activo" label="Cuenta activa" :checked="$cuenta->activo"/>
    </div>
</x-form-modal>
