<x-layouts.app title="Caja chica" breadcrumb="Caja">
    <x-slot:actions>
        <x-export :url="route('caja.chica.index')"/>
        @unless ($hayApertura)
            <button class="btn btn-secondary" data-modal-url="{{ route('caja.chica.create', ['tipo' => 'apertura']) }}" data-con-fecha data-modal-size="md"><x-heroicon-o-flag class="h-4 w-4"/> Saldo inicial</button>
        @endunless
        <button class="btn btn-secondary" data-modal-url="{{ route('caja.chica.create', ['tipo' => 'reposicion']) }}" data-con-fecha data-modal-size="md"><x-heroicon-o-arrow-down-circle class="h-4 w-4 text-emerald-700"/> Reposición de fondo</button>
        <button class="btn btn-primary" data-modal-url="{{ route('caja.chica.create') }}" data-con-fecha data-modal-size="lg"><x-heroicon-o-plus class="h-4 w-4"/> Registrar gasto</button>
    </x-slot:actions>

    @include('caja._tabs')

    @unless ($hayApertura)
        <div class="help mb-4 flex items-center justify-between border-amber-300 bg-amber-50 text-amber-900">
            <span>La caja chica aún no tiene saldo inicial. Regístrelo una sola vez; desde el día siguiente el saldo inicial se toma automáticamente del saldo final del día anterior.</span>
            <button class="btn btn-secondary btn-sm" data-modal-url="{{ route('caja.chica.create', ['tipo' => 'apertura']) }}" data-con-fecha data-modal-size="md">Registrar saldo inicial</button>
        </div>
    @endunless

    <dl class="ledger !grid-cols-2 lg:!grid-cols-4">
        <x-cifra label="Saldo inicial" :value="soles($resumen['saldo_inicial'])" :hint="'al '.fecha($desde->copy()->subDay())"/>
        <x-cifra label="(+) Reposiciones" :value="soles($resumen['reposiciones'])" tone="green" hint="transferido desde caja general"/>
        <x-cifra label="(−) Total gasto" :value="soles($resumen['gastos'])" tone="red" :hint="$resumen['filas']->where('movimiento.tipo', 'gasto')->count().' comprobante(s)'"/>
        <x-cifra label="Saldo final caja chica" :value="soles($resumen['saldo_final'])" :hint="'al '.fecha($hasta)" total/>
    </dl>

    <div class="mt-4 space-y-4">
        <x-remote-table :url="route('caja.chica.index')" title="Reporte de movimientos">
            <x-slot:header>
                <span class="text-[12px] text-slate-500">Sucursal {{ config('erp.caja_chica.sucursal') }} · Responsable {{ config('erp.caja_chica.responsable') }}</span>
            </x-slot:header>
            <x-slot:filters>
                <x-field.input name="desde" label="Desde" type="date" :value="$desde->format('Y-m-d')" class="w-40"/>
                <x-field.input name="hasta" label="Hasta" type="date" :value="$hasta->format('Y-m-d')" class="w-40"/>
                <x-field.select name="concepto" label="Concepto" :options="$conceptos" placeholder="Todos" class="w-52" :selected="request('concepto')"/>
            </x-slot:filters>
            @include('caja.chica._table')
        </x-remote-table>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card self-start">
                <div class="card-header"><p class="card-title">Gasto por concepto</p></div>
                <table class="table table-compact">
                    <tbody>
                    @forelse ($resumen['por_concepto'] as $concepto => $c)
                        <tr><td>{{ $concepto }} <span class="text-xs text-slate-400">({{ $c['cantidad'] }})</span></td><td class="text-right">{{ num($c['total'], 2) }}</td></tr>
                    @empty
                        <tr><td class="py-4 text-center text-slate-400">Sin gastos en el periodo.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($resumen['por_concepto']->isNotEmpty())
                        <tfoot><tr><td>Total gasto</td><td class="text-right">{{ num($resumen['gastos'], 2) }}</td></tr></tfoot>
                    @endif
                </table>
            </section>
            <section class="card self-start">
                <div class="card-header"><p class="card-title">Relación con caja general</p></div>
                <table class="recibo">
                    <tr><td>Saldo inicial</td><td>{{ num($resumen['saldo_inicial'], 2) }}</td></tr>
                    <tr><td>(+) Reposiciones <span class="text-xs text-slate-400">(egreso de caja general)</span></td><td>{{ num($resumen['reposiciones'], 2) }}</td></tr>
                    <tr class="resta"><td>(−) Gastos con comprobante</td><td>{{ num($resumen['gastos'], 2) }}</td></tr>
                    <tr class="final"><td>Saldo final</td><td>S/ {{ num($resumen['saldo_final'], 2) }}</td></tr>
                </table>
                <div class="flex justify-end border-t border-line px-4 py-2.5">
                    <a href="{{ route('caja.arqueos.index', ['caja' => 'chica', 'fecha' => $hasta->format('Y-m-d')]) }}" class="btn btn-secondary btn-sm"><x-heroicon-o-calculator/> Arqueo de caja chica</a>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
