<x-form-modal :title="$deposito->exists ? 'Editar depósito' : 'Nuevo depósito bancario'" subtitle="El monto sale de caja." icon="building-library"
              :action="$deposito->exists ? route('caja.depositos.update', $deposito) : route('caja.depositos.store')" :method="$deposito->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.input name="fecha" type="date" label="Fecha" :value="$deposito->fecha?->format('Y-m-d')" required/>
        <x-field.select name="cuenta_bancaria_id" label="Cuenta" :options="$cuentas" :selected="$deposito->cuenta_bancaria_id" required/>
        <x-field.input name="monto" type="number" step="0.01" min="0.01" label="Monto" prefix="S/" :value="$deposito->monto" required/>
        <x-field.input name="numero_operacion" label="N° de operación" :value="$deposito->numero_operacion"/>
        <x-field.select name="empresa_id" label="Empresa" :options="$empresas" :selected="$deposito->empresa_id" placeholder="— Según la cuenta —"/>
        <x-field.select name="chofer_id" label="Responsable" :options="$choferes" :selected="$deposito->chofer_id" placeholder="—" tom/>
        <x-field.input name="depositante" label="Quién depositó" :value="$deposito->depositante" class="sm:col-span-2"/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$deposito->observaciones" rows="2" class="sm:col-span-2"/>
    </div>
</x-form-modal>
