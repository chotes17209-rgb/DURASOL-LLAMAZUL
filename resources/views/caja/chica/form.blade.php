@php($reposicion = $m->tipo === \App\Models\CajaChicaMovimiento::REPOSICION)
<x-form-modal :title="($m->exists ? 'Editar ' : '').($reposicion ? 'Reposición de caja chica' : 'Gasto de caja chica')"
              :subtitle="$reposicion ? 'El importe se registra como egreso de la caja general.' : 'Registre el gasto con los datos de su comprobante.'"
              :action="$m->exists ? route('caja.chica.update', $m) : route('caja.chica.store')" :method="$m->exists ? 'PUT' : 'POST'">
    <input type="hidden" name="tipo" value="{{ $m->tipo }}">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field.input name="fecha" type="date" label="Fecha" :value="$m->fecha?->format('Y-m-d')" required/>
        @if ($reposicion)
            <input type="hidden" name="concepto" value="Reposición de fondo">
            <x-field.input name="monto" type="number" step="0.01" min="0.01" label="Importe repuesto" prefix="S/" :value="$m->monto" required class="sm:col-span-2"/>
            <x-field.input name="descripcion" label="Descripción" :value="$m->descripcion ?? 'REPOSICIÓN DE FONDO DE CAJA CHICA'" required class="sm:col-span-3"/>
            <x-field.input name="comprobante" label="N° documento (opcional)" :value="$m->comprobante"/>
            <x-field.input name="observacion" label="Observación" :value="$m->observacion" class="sm:col-span-2"/>
        @else
            <x-field.select name="concepto" label="Concepto" :options="$conceptos" :selected="$m->concepto" required/>
            <x-field.input name="monto" type="number" step="0.01" min="0.01" label="Monto del gasto" prefix="S/" :value="$m->monto" required/>
            <x-field.input name="descripcion" label="Descripción" :value="$m->descripcion" required class="sm:col-span-3" placeholder="Ej.: COMPRA DE LUBRICANTE"/>

            <p class="section-title sm:col-span-3 !mb-0 mt-1">Comprobante</p>
            <x-field.input name="comprobante" label="N° comprobante" :value="$m->comprobante" placeholder="F001-00011025"/>
            <x-field.input name="ruc" label="RUC / DNI" :value="$m->ruc" maxlength="11"/>
            <x-field.input name="proveedor" label="Proveedor" :value="$m->proveedor"/>

            <p class="section-title sm:col-span-3 !mb-0 mt-1">Vehículo y conductor</p>
            <x-field.select name="vehiculo_id" label="Vehículo" :options="$vehiculos" :selected="$m->vehiculo_id" placeholder="— Ninguno —"/>
            <x-field.select name="chofer_id" label="Conductor" :options="$choferes" :selected="$m->chofer_id" placeholder="— Ninguno —" class="sm:col-span-2"/>
            <x-field.input name="observacion" label="Observación" :value="$m->observacion" class="sm:col-span-3" placeholder="Ej.: realizado por Rufino, vale 25/09"/>
        @endif
    </div>
</x-form-modal>
