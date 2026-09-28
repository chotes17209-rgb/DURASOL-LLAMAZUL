<x-layouts.app title="Kardex de balones" breadcrumb="Logística">
    <x-slot:actions>
        <a href="{{ route('logistica.stock') }}" class="btn btn-secondary">Volver al stock</a>
    </x-slot:actions>
    <x-remote-table :url="route('logistica.stock.kardex')">
        <x-slot:filters>
            <div>
                <label class="form-label">Concepto</label>
                <select name="llave" class="form-input w-48">
                    @foreach (\App\Services\AlmacenService::STOCK as $clave => [$tipo, $col, $titulo])
                        <option value="{{ $clave }}" @selected($clave === $llave)>{{ $tipo === 'lleno' ? 'Llenos' : 'Vacíos' }} · {{ $titulo }}</option>
                    @endforeach
                </select>
            </div>
            <x-field.input name="desde" label="Desde" type="date" :value="$desde->toDateString()" class="w-40"/>
            <x-field.input name="hasta" label="Hasta" type="date" :value="$hasta->toDateString()" class="w-40"/>
        </x-slot:filters>
        @include('logistica.stock._kardex')
    </x-remote-table>
</x-layouts.app>
