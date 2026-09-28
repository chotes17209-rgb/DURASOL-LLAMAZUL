<x-layouts.app title="Partes diarios" breadcrumb="Logística">
    <x-slot:actions>
        <form method="GET" action="{{ route('logistica.partes.abrir') }}" class="flex items-center gap-2">
            <input type="date" name="fecha" value="{{ today()->toDateString() }}" class="form-input w-36">
            <button class="btn btn-primary">Abrir parte</button>
        </form>
    </x-slot:actions>
    @php($c = $hoy['control'])
    <dl class="ledger mb-4 !grid-cols-2 lg:!grid-cols-6">
        @php($t = \App\Services\AlmacenService::totalesPorPresentacion($c))
        <x-cifra label="Total S-10" :value="num($t['S10'])" :hint="'llenos '.num($c['lleno_s10']['final']).' + cambios '.num($c['cambio_s10']['final'])" total/>
        <x-cifra label="Total S-45" :value="num($t['S45'])" :hint="'llenos '.num($c['lleno_s45']['final']).' + cambios '.num($c['cambio_s45']['final'])"/>
        <x-cifra label="Total M-10" :value="num($t['M10'])" :hint="'llenos '.num($c['lleno_m10']['final']).' + cambios '.num($c['cambio_m10']['final'])"/>
        @php($v = \App\Services\AlmacenService::totalesVacios($c))
        <x-cifra label="Vacíos S-10" :value="num($v['S10'])" :hint="'plomos '.num($c['plomo_s10']['final']).' + colores '.num($c['color_s10']['final'])"/>
        <x-cifra label="Último parte" :value="$hoy['ultimo'] ? fecha($hoy['ultimo']) : '—'"/>
        <x-cifra label="Partes abiertos" :value="num($hoy['abiertos'])" :tone="$hoy['abiertos'] ? 'red' : 'green'" hint="pendientes de cerrar"/>
    </dl>
    <x-remote-table :url="route('logistica.partes.index')">
        <x-slot:filters>
            <div><label class="form-label">Mes</label><input type="month" name="mes" value="{{ request('mes') }}" class="form-input w-40" title="Deja vacío para ver todos"></div>
        </x-slot:filters>
        @include('logistica.partes._table')
    </x-remote-table>
</x-layouts.app>
