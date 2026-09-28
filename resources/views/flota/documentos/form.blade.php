<x-form-modal :title="($documento->exists ? 'Editar documento' : 'Nuevo documento').' · '.$vehiculo->placa" icon="document-check"
              :action="$documento->exists ? route('documentos.update', $documento) : route('documentos.store', $vehiculo)" :method="$documento->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field.select name="tipo" label="Tipo de documento" :options="\App\Enums\TipoDocumentoVehicular::options()" :selected="$documento->tipo" :empty="false" required/>
        <x-field.input name="numero" label="Número" :value="$documento->numero"/>
        <x-field.input name="entidad" label="Entidad / aseguradora" :value="$documento->entidad" class="sm:col-span-2"/>
        <x-field.input name="fecha_emision" type="date" label="Emisión" :value="$documento->fecha_emision?->format('Y-m-d')"/>
        <x-field.input name="fecha_vencimiento" type="date" label="Vencimiento" :value="$documento->fecha_vencimiento?->format('Y-m-d')"/>
        <x-field.input name="costo" type="number" step="0.01" min="0" label="Costo" prefix="S/" :value="$documento->costo"/>
        <x-field.input name="archivo" type="file" label="Archivo (PDF o imagen)" accept=".pdf,image/*" :hint="$documento->archivo ? 'Ya tiene un archivo; sube otro solo si quieres reemplazarlo.' : 'Máximo 5 MB.'"/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$documento->observaciones" rows="2" class="sm:col-span-2"/>
    </div>
</x-form-modal>
