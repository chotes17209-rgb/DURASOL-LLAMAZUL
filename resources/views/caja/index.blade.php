<x-layouts.app title="Caja general" breadcrumb="Caja">
    <x-slot:actions>
        <button class="btn btn-secondary" data-modal-url="{{ route('caja.movimientos.create', ['tipo' => 'ingreso']) }}" data-con-fecha data-modal-size="md"><x-heroicon-o-arrow-down-circle class="h-4 w-4 text-emerald-700"/> Ingreso</button>
        <button class="btn btn-secondary" data-modal-url="{{ route('caja.movimientos.create', ['tipo' => 'egreso']) }}" data-con-fecha data-modal-size="md"><x-heroicon-o-arrow-up-circle class="h-4 w-4 text-red-700"/> Gasto / egreso</button>
        <button class="btn btn-primary" data-modal-url="{{ route('caja.depositos.create') }}" data-con-fecha data-modal-size="md"><x-heroicon-o-building-library class="h-4 w-4"/> Depósito</button>
    </x-slot:actions>

    @include('caja._tabs')

    <dl class="ledger !grid-cols-2 lg:!grid-cols-4">
        <x-cifra label="Saldo inicial" :value="soles($resumen['saldo_inicial'])" :hint="'al '.fecha($desde->copy()->subDay())"/>
        <x-cifra label="(+) Ingresos" :value="soles($resumen['ingresos'])" tone="green" hint="liquidaciones, cobranzas y otros"/>
        <x-cifra label="(−) Egresos" :value="soles($resumen['egresos'])" tone="red" hint="depósitos, gastos y pagos"/>
        <x-cifra label="Saldo en caja" :value="soles($resumen['saldo_final'])" :hint="'al '.fecha($hasta)" total/>
    </dl>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-remote-table :url="route('caja.index')">
                <x-slot:filters>
                    <x-field.input name="desde" label="Desde" type="date" :value="$desde->format('Y-m-d')" class="w-40"/>
                    <x-field.input name="hasta" label="Hasta" type="date" :value="$hasta->format('Y-m-d')" class="w-40"/>
                    <x-field.select name="tipo" label="Tipo" :options="['ingreso' => 'Ingresos', 'egreso' => 'Egresos']" placeholder="Todos" class="w-36" :selected="request('tipo')"/>
                    <x-field.select name="categoria" label="Categoría" :options="\App\Enums\CategoriaCaja::options()" placeholder="Todas" class="w-52" :selected="request('categoria')"/>
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
                            <span class="font-semibold tabular-nums {{ $c->tipo === 'egreso' ? 'text-red-700' : 'text-emerald-700' }}">{{ $c->tipo === 'egreso' ? '−' : '+' }}{{ soles($c->total) }}</span>
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
                            <div class="min-w-0"><p class="font-semibold text-slate-900">{{ $l->chofer->alias }} · <span class="font-mono text-[12px]">{{ $l->codigo }}</span></p><p class="text-xs text-slate-500">Venta {{ fecha($l->fecha_venta) }} · <span class="whitespace-nowrap">a entregar {{ soles($l->efectivoAEntregar()) }}</span></p></div>
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
