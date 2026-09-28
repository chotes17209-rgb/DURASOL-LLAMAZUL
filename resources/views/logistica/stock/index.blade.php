<x-layouts.app title="Stock de almacén" breadcrumb="Logística">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-xs text-slate-500">Al</label>
            <input type="date" name="fecha" value="{{ $fecha->toDateString() }}" class="form-input w-36" onchange="this.form.submit()">
        </form>
        <x-export :url="route('logistica.stock', ['fecha' => $fecha->toDateString()])"/>
        <a href="{{ route('logistica.stock.kardex') }}" class="btn btn-secondary">Kardex</a>
        <a href="{{ route('logistica.partes.show', $fecha->toDateString()) }}" class="btn btn-primary">Parte del día</a>
    </x-slot:actions>

    @php($grupos = ['Llenos' => ['lleno_s10', 'lleno_s45', 'lleno_m10'], 'Cambios (fallados)' => ['cambio_s10', 'cambio_s45', 'cambio_m10'], 'Vacíos' => ['plomo_s10', 'plomo_s45', 'color_s10', 'color_s45']])
    <div class="grid gap-4 xl:grid-cols-3">
        @foreach ($grupos as $titulo => $llaves)
            <div class="card">
                <div class="card-header"><p class="card-title">{{ $titulo }}</p></div>
                <table class="table table-compact table-grid">
                    <thead><tr><th></th><th class="text-right">Inicial</th><th class="text-right">Ingreso</th><th class="text-right">Salida</th><th class="text-right">Final</th></tr></thead>
                    <tbody>
                    @foreach ($llaves as $llave)
                        @php($f = $control[$llave])
                        <tr>
                            <td><a class="text-brand-700 hover:underline" href="{{ route('logistica.stock.kardex', ['llave' => $llave]) }}">{{ $f['titulo'] }}</a></td>
                            <td class="text-right">{{ num($f['inicial']) }}</td><td class="text-right">{{ num($f['ingreso']) }}</td><td class="text-right">{{ num($f['salida']) }}</td>
                            <td class="text-right font-semibold {{ $f['final'] < 0 ? 'text-red-700' : '' }}">{{ num($f['final']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <div class="card mt-4 max-w-2xl">
        <div class="card-header"><p class="card-title">Stock disponible por empresa (compras en planta − ventas liquidadas)</p></div>
        <table class="table table-compact table-grid">
            <thead><tr><th>Empresa</th><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">M-10</th></tr></thead>
            <tbody>
            @foreach ($porEmpresa as $empresa => $s)
                <tr><td class="font-semibold">{{ $empresa }}</td>@foreach (['S10', 'S45', 'M10'] as $c)<td class="text-right {{ $s[$c] < 0 ? 'text-red-700' : '' }}">{{ num($s[$c]) }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
        <p class="px-4 py-2 text-xs text-slate-500">Considera las compras registradas en los partes (filas de planta con empresa) y las ventas de liquidaciones registradas en el sistema.</p>
    </div>
</x-layouts.app>
