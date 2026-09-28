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
        $vacios = \App\Services\AlmacenService::totalesVacios($control);
        $f = fn ($llave, $campo) => num($control[$llave][$campo]);
    @endphp
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-5">
        <x-cifra label="Total S-10" :value="num($total['S10'])" :hint="'llenos '.$f('lleno_s10', 'final').' + cambios '.$f('cambio_s10', 'final')"/>
        <x-cifra label="Total S-45" :value="num($total['S45'])" :hint="'llenos '.$f('lleno_s45', 'final').' + cambios '.$f('cambio_s45', 'final')"/>
        <x-cifra label="Total M-10" :value="num($total['M10'])" :hint="'llenos '.$f('lleno_m10', 'final').' + cambios '.$f('cambio_m10', 'final')"/>
        <x-cifra label="Vacíos S-10" :value="num($vacios['S10'])" :hint="'plomos '.$f('plomo_s10', 'final').' + colores '.$f('color_s10', 'final')"/>
        <x-cifra label="Vacíos S-45" :value="num($vacios['S45'])" :hint="'plomos '.$f('plomo_s45', 'final').' + colores '.$f('color_s45', 'final')"/>
    </dl>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        {{-- Igual al cuadro CONTROL DE STOCK LLENOS de la hoja de logística --}}
        <div class="card">
            <div class="card-header"><p class="card-title">Control de stock llenos</p><span class="text-[12px] text-slate-500">al {{ $fecha->format('d/m/Y') }}</span></div>
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
            <div class="card-header"><p class="card-title">Total</p><span class="text-[12px] text-slate-500">llenos + cambios</span></div>
            <table class="table table-grid">
                <thead><tr><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">M-10</th></tr></thead>
                <tbody><tr>@foreach ($total as $v)<td class="text-right text-[18px] font-semibold text-brand-950">{{ num($v) }}</td>@endforeach</tr></tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="card">
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
        <div class="card">
            <div class="card-header"><p class="card-title">Total vacíos</p><span class="text-[12px] text-slate-500">plomos + colores</span></div>
            <table class="table table-grid">
                <thead><tr><th class="text-right">S-10</th><th class="text-right">S-45</th></tr></thead>
                <tbody><tr>@foreach ($vacios as $v)<td class="text-right text-[18px] font-semibold text-brand-950">{{ num($v) }}</td>@endforeach</tr></tbody>
            </table>
        </div>
    </div>

</x-layouts.app>
