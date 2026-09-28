<x-form-modal :title="'Precios de compra · '.$instalacion->codigo" :subtitle="$instalacion->empresa?->nombre.' · '.($instalacion->responsable ?: $instalacion->nombre).' · '.($instalacion->planta ?: '')"
              :action="route('precios.compra.store')">
    <input type="hidden" name="instalacion_id" value="{{ $instalacion->id }}">
    <table class="table table-compact table-grid mb-4">
        <thead><tr><th>Producto</th><th class="text-right">Precio actual</th><th>Desde</th><th>Factura</th><th class="w-32 text-right">Nuevo precio</th></tr></thead>
        <tbody>
        @foreach ($productos as $p)
            @php($v = $vigentes[$p->id] ?? null)
            <tr>
                <td class="font-medium">{{ $p->codigo }} <span class="text-xs text-slate-500">{{ $p->nombre }}</span></td>
                <td class="text-right">{{ $v ? num($v->precio, 2) : '—' }}</td>
                <td class="text-xs">{{ $v ? fecha($v->vigente_desde) : '' }}</td>
                <td>@if ($v)@if ($v->validado)<span class="badge badge-green">Validado</span>@else<span class="badge badge-amber">No validado</span>@endif @endif</td>
                <td class="!p-0"><input type="number" step="0.01" min="0" name="precios[{{ $p->id }}]" value="{{ $v?->precio }}" class="cell-input"></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field.input name="vigente_desde" type="date" label="Vigente desde" :value="today()->format('Y-m-d')" required/>
        <x-field.input name="motivo" label="Motivo" placeholder="Ej.: variación de precio Solgas"/>
    </div>
</x-form-modal>
