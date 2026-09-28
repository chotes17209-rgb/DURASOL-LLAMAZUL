<x-form-modal :title="$compra->exists ? 'Editar compra' : 'Registrar compra en planta'" :subtitle="auth()->user()->can('ver-precios-compra') ? 'El precio se propone según la instalación o el promedio vigente de la empresa.' : 'Registre la cantidad de balones recibidos de planta.'"
              :action="$compra->exists ? route('compras.update', $compra) : route('compras.store')" :method="$compra->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-2" x-data="{
            f: { fecha: '{{ $compra->fecha?->format('Y-m-d') }}', empresa_id: '{{ $compra->empresa_id }}', producto_id: '{{ $compra->producto_id }}', instalacion_id: '{{ $compra->instalacion_id }}' },
            async sugerir() {
                if (!this.$refs.precio || !this.f.fecha || !this.f.empresa_id || !this.f.producto_id) return;
                const r = await window.request('{{ route('compras.precio') }}?' + new URLSearchParams(this.f), { json: true });
                if (r.precio !== null && !{{ $compra->exists ? 'true' : 'false' }}) this.$refs.precio.value = Number(r.precio).toFixed(2);
            }
        }" x-init="$watch('f', () => sugerir())">
        <div>
            <label class="form-label">Fecha <span class="text-red-700">*</span></label>
            <input type="date" name="fecha" class="form-input" x-model="f.fecha" max="{{ today()->format('Y-m-d') }}" required>
        </div>
        <div>
            <label class="form-label">Empresa <span class="text-red-700">*</span></label>
            <select name="empresa_id" class="form-input" x-model="f.empresa_id" required>
                <option value="">Seleccionar...</option>
                @foreach ($empresas as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Presentación <span class="text-red-700">*</span></label>
            <select name="producto_id" class="form-input" x-model="f.producto_id" required>
                <option value="">Seleccionar...</option>
                @foreach ($productos as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Instalación</label>
            <select name="instalacion_id" class="form-input" x-model="f.instalacion_id">
                <option value="">— Sin especificar —</option>
                @foreach ($instalaciones as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
            </select>
        </div>
        <x-field.input name="cantidad" type="number" min="1" label="Cantidad (balones)" :value="$compra->cantidad" required/>
        @can('ver-precios-compra')
            <div>
                <label class="form-label">Precio unitario (S/)</label>
                <input type="number" step="0.01" min="0" name="precio_unitario" x-ref="precio" class="form-input" value="{{ $compra->precio_unitario }}">
            </div>
        @endcan
        <x-field.input name="documento" label="Guía / factura" :value="$compra->documento"/>
        <x-field.input name="observacion" label="Observación" :value="$compra->observacion"/>
    </div>
</x-form-modal>
