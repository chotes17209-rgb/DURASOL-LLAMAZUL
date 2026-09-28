<x-layouts.app title="Guías de planta" breadcrumb="Logística · Movimiento de masa">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-url="{{ route('logistica.guias.create') }}" data-modal-size="xl"><x-heroicon-o-plus class="h-4 w-4"/> Nueva guía (salida a planta)</button>
    </x-slot:actions>
    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 p-5 text-white shadow-lg shadow-brand-600/20 md:col-span-2">
            <p class="text-sm font-semibold text-brand-100">¿Cómo funciona?</p>
            <ol class="mt-2 space-y-1 text-sm text-white/90">
                <li><b>1. Salida:</b> registra la guía con los balones comprados y los vacíos (plomo, color) o cambios que llevas. Deben ser la misma cantidad.</li>
                <li><b>2. Retorno:</b> al volver indica cuántos llenos entraron. Si la planta no aceptó vacíos, regístralos como rechazados.</li>
                <li><b>3. Control de masa:</b> lo que salió debe volver completo; el stock se actualiza solo.</li>
            </ol>
        </div>
        <x-kpi label="Guías en tránsito" :value="$enTransito" icon="truck" color="amber" hint="Camiones en planta o en camino"/>
    </div>
    <x-remote-table :url="route('logistica.guias.index')">
        <x-slot:filters>
            <x-search placeholder="N° de guía..."/>
            <x-field.select name="empresa_id" :options="$empresas" placeholder="Todas las empresas" class="w-48" :selected="request('empresa_id')"/>
            <x-field.select name="estado" :options="\App\Enums\EstadoGuia::options()" placeholder="Todos los estados" class="w-44" :selected="request('estado')"/>
            <x-field.input name="desde" type="date" :value="request('desde')" class="w-40"/>
            <x-field.input name="hasta" type="date" :value="request('hasta')" class="w-40"/>
        </x-slot:filters>
        @include('logistica.guias._table')
    </x-remote-table>
</x-layouts.app>
