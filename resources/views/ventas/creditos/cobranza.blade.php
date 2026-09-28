<x-form-modal title="Registrar cobranza" subtitle="El pago se aplica a las deudas más antiguas del cliente. Si es en efectivo, entra a caja." icon="banknotes" :action="route('creditos.cobranzas.store')" submit="Registrar cobranza">
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="form-label">Cliente <span class="text-rose-500">*</span></label>
            <select name="cliente_id" data-tom data-remote="{{ route('buscar.clientes') }}" placeholder="Buscar cliente..." class="form-input" required>
                @if ($cliente)<option value="{{ $cliente->id }}" selected>{{ $cliente->codigo }} · {{ $cliente->nombreMostrar() }}</option>@endif
            </select>
            @if ($cliente)<p class="form-hint">Deuda pendiente: <b class="text-rose-600">{{ soles($deuda) }}</b></p>@endif
            <p class="form-error hidden" data-error-for="cliente_id"></p>
        </div>
        <x-field.input name="fecha" type="date" label="Fecha" :value="today()->format('Y-m-d')" required/>
        <x-field.input name="monto" type="number" step="0.01" min="0.01" label="Monto" prefix="S/" :value="$deuda" required/>
        <x-field.select name="metodo_pago" label="Método de pago" :options="\App\Enums\MetodoPago::options()" selected="efectivo" :empty="false" required/>
        <x-field.input name="numero_operacion" label="N° de operación"/>
    </div>
</x-form-modal>
