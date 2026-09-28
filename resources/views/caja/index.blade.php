<x-layouts.app title="Caja general" breadcrumb="Caja">
    <x-slot:actions>
        <button class="btn btn-secondary" data-modal-url="{{ route('caja.movimientos.create', ['tipo' => 'ingreso']) }}" data-modal-size="md"><x-heroicon-o-arrow-down-circle class="h-4 w-4 text-emerald-600"/> Ingreso</button>
        <button class="btn btn-secondary" data-modal-url="{{ route('caja.movimientos.create', ['tipo' => 'egreso']) }}" data-modal-size="md"><x-heroicon-o-arrow-up-circle class="h-4 w-4 text-rose-600"/> Gasto / egreso</button>
        <button class="btn btn-primary" data-modal-url="{{ route('caja.depositos.create') }}" data-modal-size="md"><x-heroicon-o-building-library class="h-4 w-4"/> Depósito</button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Saldo inicial" :value="soles($resumen['saldo_inicial'])" icon="archive-box" color="slate" :hint="'al '.fecha($desde->copy()->subDay())"/>
        <x-kpi label="Ingresos" :value="soles($resumen['ingresos'])" icon="arrow-down-circle" color="green"/>
        <x-kpi label="Egresos" :value="soles($resumen['egresos'])" icon="arrow-up-circle" color="red"/>
        <x-kpi label="Saldo en caja" :value="soles($resumen['saldo_final'])" icon="banknotes" color="brand" :hint="'al '.fecha($hasta)"/>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-remote-table :url="route('caja.index')">
                <x-slot:filters>
                    <x-field.input name="desde" label="Desde" type="date" :value="$desde->format('Y-m-d')" class="w-40"/>
                    <x-field.input name="hasta" label="Hasta" type="date" :value="$hasta->format('Y-m-d')" class="w-40"/>
                    <x-field.select name="tipo" label="Tipo" :options="['ingreso' => 'Ingresos', 'egreso' => 'Egresos']" placeholder="Todos" class="w-36" :selected="request('tipo')"/>
                    <x-field.select name="categoria" label="Categoría" :options="\App\Enums\CategoriaCaja::options()" placeholder="Todas" class="w-48" :selected="request('categoria')"/>
                </x-slot:filters>
                @include('caja._table')
            </x-remote-table>
        </div>
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><p class="card-title">Por categoría</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse ($resumen['por_categoria'] as $c)
                        <div class="flex items-center justify-between px-5 py-3 text-sm">
                            <span>{{ $c->categoria->label() }} <span class="text-xs text-slate-400">({{ $c->cantidad }})</span></span>
                            <span class="font-semibold tabular-nums {{ $c->tipo === 'egreso' ? 'text-rose-600' : 'text-emerald-600' }}">{{ $c->tipo === 'egreso' ? '−' : '+' }}{{ soles($c->total) }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-slate-400">Sin movimientos.</p>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <div class="card-header"><p class="card-title">Liquidaciones por cerrar</p><x-badge color="amber">{{ $porCerrar->count() }}</x-badge></div>
                <div class="divide-y divide-slate-100">
                    @forelse ($porCerrar as $l)
                        <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div><p class="font-semibold">{{ $l->chofer->alias }} · {{ $l->codigo }}</p><p class="text-xs text-slate-500">Venta {{ fecha($l->fecha_venta) }} · esperado {{ soles($l->efectivo_esperado) }}</p></div>
                            <button class="btn btn-success btn-sm" data-modal-url="{{ route('liquidaciones.cerrar', $l) }}" data-modal-size="md">Cerrar</button>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-slate-400">Todo está cerrado.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
