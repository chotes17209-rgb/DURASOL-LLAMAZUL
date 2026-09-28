<x-layouts.app title="Despachos a choferes" breadcrumb="Logística · Movimiento de masa">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('logistica.despachos.create') }}" data-modal-size="xl"><x-heroicon-o-truck class="h-4 w-4"/> Nueva salida</button>
    </x-slot:actions>
    @if ($enRuta->isNotEmpty())
        <div class="mb-5 card">
            <div class="card-header"><p class="card-title">Choferes en ruta ahora</p><x-badge color="amber">{{ $enRuta->count() }}</x-badge></div>
            <div class="flex flex-wrap gap-3 p-5">
                @foreach ($enRuta as $d)
                    <button type="button" data-modal-url="{{ route('logistica.despachos.retorno', $d) }}" data-modal-size="xl"
                            class="group flex items-center gap-3 rounded-2xl bg-amber-50 px-4 py-3 text-left ring-1 ring-amber-200 transition hover:bg-amber-100">
                        <span class="relative flex h-3 w-3"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex h-3 w-3 rounded-full bg-amber-500"></span></span>
                        <span><span class="block text-sm font-bold text-slate-900">{{ $d->chofer->alias }} · vuelta {{ $d->vuelta }}</span>
                            <span class="text-xs text-slate-500">Salió {{ fecha($d->fecha) }} {{ $d->hora_salida ? substr($d->hora_salida, 0, 5) : '' }} · clic para registrar retorno</span></span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
    <x-remote-table :url="route('logistica.despachos.index')">
        <x-slot:filters>
            <x-field.select name="chofer_id" :options="$choferes" placeholder="Todos los choferes" class="w-52" :selected="request('chofer_id')"/>
            <x-field.select name="estado" :options="\App\Enums\EstadoDespacho::options()" placeholder="Todos los estados" class="w-44" :selected="request('estado')"/>
            <x-field.input name="fecha" type="date" :value="request('fecha')" class="w-44"/>
        </x-slot:filters>
        @include('logistica.despachos._table')
    </x-remote-table>
</x-layouts.app>
