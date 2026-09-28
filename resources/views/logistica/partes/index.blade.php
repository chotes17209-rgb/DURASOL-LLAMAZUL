<x-layouts.app title="Partes diarios" breadcrumb="Logística">
    <x-slot:actions>
        <form method="GET" action="{{ route('logistica.partes.abrir') }}" class="flex items-center gap-2">
            <input type="date" name="fecha" value="{{ today()->toDateString() }}" class="form-input w-36">
            <button class="btn btn-primary">Abrir parte</button>
        </form>
    </x-slot:actions>
    @php($c = $hoy['control'])
    <dl class="ledger mb-4">
        <x-cifra label="Llenos S-10" :value="num($c['lleno_s10']['final'])"/>
        <x-cifra label="Llenos S-45" :value="num($c['lleno_s45']['final'])"/>
        <x-cifra label="Llenos M-10" :value="num($c['lleno_m10']['final'])"/>
        <x-cifra label="Vacíos S-10" :value="num($c['plomo_s10']['final'] + $c['color_s10']['final'])" :hint="'plomo '.num($c['plomo_s10']['final']).' · color '.num($c['color_s10']['final'])"/>
        <x-cifra label="Último parte" :value="$hoy['ultimo'] ? fecha($hoy['ultimo']) : '—'"/>
        <x-cifra label="Partes abiertos" :value="num($hoy['abiertos'])" :tone="$hoy['abiertos'] ? 'red' : 'green'" hint="pendientes de cerrar"/>
        <x-cifra label="Stock al día de hoy" :value="num($c['lleno_s10']['final'] + $c['lleno_s45']['final'] + $c['lleno_m10']['final'])" hint="total de llenos" total/>
    </dl>
    <x-remote-table :url="route('logistica.partes.index')">
        <x-slot:filters>
            <div><label class="form-label">Mes</label><input type="month" name="mes" value="{{ request('mes') }}" class="form-input w-40" title="Deja vacío para ver todos"></div>
        </x-slot:filters>
        @include('logistica.partes._table')
    </x-remote-table>
</x-layouts.app>
