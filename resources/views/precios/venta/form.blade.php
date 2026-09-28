<x-form-modal :title="'Precios de venta · '.$cliente->nombreMostrar()" :subtitle="'Código '.$cliente->codigo.' · '.($cliente->chofer?->alias ? 'Chofer '.$cliente->chofer->alias : 'Sin chofer asignado')" :action="route('precios.venta.update', $cliente)" method="PUT">
    <p class="section-title">Lista de precios del cliente</p>
    <table class="table table-compact table-grid mb-4">
        <thead><tr><th>Producto</th><th class="w-32 text-right">Precio actual</th><th class="w-36 text-right">Nuevo precio (S/)</th></tr></thead>
        <tbody>
        @foreach ($productos as $p)
            <tr>
                <td><span class="font-semibold text-brand-900">{{ $p->codigo }}</span> <span class="text-xs text-slate-500">{{ $p->nombre }}</span></td>
                <td class="text-right">{{ isset($vigentes[$p->id]) ? num($vigentes[$p->id], 2) : '—' }}</td>
                <td class="!p-0"><input type="number" step="0.01" min="0" name="precios[{{ $p->id }}]" value="{{ isset($vigentes[$p->id]) ? number_format($vigentes[$p->id], 2, '.', '') : '' }}" class="cell-input font-semibold"></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field.input name="vigente_desde" type="date" label="Vigente desde" :value="$vigenteDesde->format('Y-m-d')" required/>
        <x-field.input name="motivo" label="Motivo del cambio" placeholder="Ej.: variación de precio Solgas"/>
    </div>
    <p class="form-hint">Solo se registran los precios que cambien. El precio anterior queda en el historial del cliente con su fecha.</p>
</x-form-modal>
