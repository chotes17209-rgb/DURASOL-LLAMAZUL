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

    @php
        $total = \App\Services\AlmacenService::totalesPorPresentacion($control);
        $f = fn ($llave, $campo) => num($control[$llave][$campo]);
    @endphp
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-5">
        <x-cifra label="Total S-10" :value="num($total['S10'])" :hint="'llenos '.$f('lleno_s10', 'final').' + cambios '.$f('cambio_s10', 'final')" total/>
        <x-cifra label="Total S-45" :value="num($total['S45'])" :hint="'llenos '.$f('lleno_s45', 'final').' + cambios '.$f('cambio_s45', 'final')"/>
        <x-cifra label="Total M-10" :value="num($total['M10'])" :hint="'llenos '.$f('lleno_m10', 'final').' + cambios '.$f('cambio_m10', 'final')"/>
        <x-cifra label="Vacíos plomo" :value="num($control['plomo_s10']['final'] + $control['plomo_s45']['final'])" :hint="'S-10 '.$f('plomo_s10', 'final').' · S-45 '.$f('plomo_s45', 'final')"/>
        <x-cifra label="Vacíos de color" :value="num($control['color_s10']['final'] + $control['color_s45']['final'])" :hint="'S-10 '.$f('color_s10', 'final').' · S-45 '.$f('color_s45', 'final')"/>
    </dl>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        {{-- Igual al cuadro CONTROL DE STOCK LLENOS de la hoja de logística --}}
        <div class="card">
            <div class="card-header"><p class="card-title">Control de stock llenos</p><span class="text-[11px] text-slate-500">al {{ $fecha->format('d/m/Y') }}</span></div>
            <table class="table table-compact table-grid">
                <thead>
                <tr class="th-group"><th></th><th colspan="2">Solgas</th><th>Masgas</th><th colspan="3">Cambios</th></tr>
                <tr><th>Stock</th><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">M-10</th><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">M-10</th></tr>
                </thead>
                <tbody>
                @foreach (['inicial' => 'Stock inicial', 'ingreso' => '(+) Ingreso', 'salida' => '(−) Salida'] as $campo => $etiqueta)
                    <tr>
                        <td class="font-medium">{{ $etiqueta }}</td>
                        @foreach (['lleno_s10', 'lleno_s45', 'lleno_m10', 'cambio_s10', 'cambio_s45', 'cambio_m10'] as $llave)<td class="text-right">{{ $f($llave, $campo) }}</td>@endforeach
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr><td>FINAL</td>@foreach (['lleno_s10', 'lleno_s45', 'lleno_m10', 'cambio_s10', 'cambio_s45', 'cambio_m10'] as $llave)<td class="text-right {{ $control[$llave]['final'] < 0 ? 'text-red-700' : '' }}"><a href="{{ route('logistica.stock.kardex', ['llave' => $llave]) }}" class="hover:underline">{{ $f($llave, 'final') }}</a></td>@endforeach</tr>
                </tfoot>
            </table>
        </div>
        <div class="card">
            <div class="card-header"><p class="card-title">Total</p><span class="text-[11px] text-slate-500">llenos + cambios</span></div>
            <table class="table table-grid">
                <thead><tr><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">M-10</th></tr></thead>
                <tbody><tr>@foreach ($total as $v)<td class="text-right text-[18px] font-semibold text-brand-950">{{ num($v) }}</td>@endforeach</tr></tbody>
            </table>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><p class="card-title">Control de stock vacíos</p></div>
        <table class="table table-compact table-grid">
            <thead>
            <tr class="th-group"><th></th><th colspan="2">Plomo</th><th colspan="2">Color</th></tr>
            <tr><th>Stock</th><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">S-10</th><th class="text-right">S-45</th></tr>
            </thead>
            <tbody>
            @foreach (['inicial' => 'Stock inicial', 'ingreso' => '(+) Ingreso', 'salida' => '(−) Salida'] as $campo => $etiqueta)
                <tr><td class="font-medium">{{ $etiqueta }}</td>@foreach (['plomo_s10', 'plomo_s45', 'color_s10', 'color_s45'] as $llave)<td class="text-right">{{ $f($llave, $campo) }}</td>@endforeach</tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>FINAL</td>@foreach (['plomo_s10', 'plomo_s45', 'color_s10', 'color_s45'] as $llave)<td class="text-right"><a href="{{ route('logistica.stock.kardex', ['llave' => $llave]) }}" class="hover:underline">{{ $f($llave, 'final') }}</a></td>@endforeach</tr></tfoot>
        </table>
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
