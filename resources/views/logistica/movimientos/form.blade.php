<x-form-modal :title="$movimiento->exists ? 'Editar movimiento' : 'Nuevo movimiento de stock'" icon="adjustments-horizontal"
              :action="$movimiento->exists ? route('logistica.movimientos.update', $movimiento) : route('logistica.movimientos.store')" :method="$movimiento->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2" x-data="{ estado: '{{ $movimiento->estado?->value ?? 'vacio' }}' }">
        <x-field.input name="fecha" type="date" label="Fecha" :value="$movimiento->fecha?->format('Y-m-d')" required/>
        <x-field.select name="tipo" label="Motivo" :options="\App\Enums\TipoMovimientoManual::options()" :selected="$movimiento->tipo" :empty="false" required/>
        <x-field.select name="sentido" label="Entrada o salida" :options="['entrada' => 'Entrada (+)', 'salida' => 'Salida (−)']" :selected="$movimiento->sentido" :empty="false" required/>
        <div>
            <label class="form-label">Estado del balón <span class="text-rose-500">*</span></label>
            <select name="estado" class="form-input" x-model="estado">
                @foreach (\App\Enums\EstadoStock::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <x-field.select name="producto_id" label="Producto" :options="$productos" :selected="$movimiento->producto_id" required hint="Para vacíos basta elegir un producto de 10 o 45 kg."/>
        <div x-show="estado === 'lleno' || estado === 'cambio'">
            <x-field.select name="empresa_id" label="Empresa" :options="$empresas" :selected="$movimiento->empresa_id"/>
        </div>
        <x-field.input name="cantidad" type="number" min="1" label="Cantidad" :value="$movimiento->cantidad" required/>
        <x-field.input name="referencia" label="Referencia (quién / de dónde)" :value="$movimiento->referencia" placeholder="Ej.: Ejército, PNP Chilca"/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$movimiento->observaciones" rows="2" class="sm:col-span-2"/>
    </div>
</x-form-modal>
