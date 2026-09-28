<x-layouts.app :title="$liquidacion->exists ? 'Liquidación '.$liquidacion->codigo : 'Nueva liquidación'" breadcrumb="Ventas · Liquidaciones">
    <x-slot:actions>
        <a href="{{ route('liquidaciones.index') }}" class="btn btn-secondary"><x-heroicon-o-arrow-left class="h-4 w-4"/> Volver</a>
        @if ($liquidacion->exists)
            <button class="btn btn-secondary" data-modal-url="{{ route('liquidaciones.show', $liquidacion) }}" data-modal-size="xl"><x-heroicon-o-eye class="h-4 w-4"/> Ver</button>
        @endif
    </x-slot:actions>

<div x-data="liquidacionEditor(@js($config))" x-cloak>
    @unless ($liquidacion->esEditable())
        <div class="mb-5 flex items-center gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
            <x-heroicon-o-lock-closed class="h-5 w-5"/>
            <p>Esta liquidación está <b>{{ $liquidacion->estado->label() }}</b> y no se puede modificar.
                @if (auth()->user()->isAdmin() && $liquidacion->estado === \App\Enums\EstadoLiquidacion::Cerrada) Un administrador puede reabrirla desde «Ver». @endif</p>
        </div>
    @endunless

    <fieldset :disabled="!editable" class="grid gap-6 2xl:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            {{-- 1. Cabecera --}}
            <div class="card card-body">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="form-label">Fecha de venta *</label>
                        <input type="date" class="form-input" x-model="cab.fecha_venta" max="{{ today()->format('Y-m-d') }}">
                    </div>
                    <div>
                        <label class="form-label">Fecha de liquidación *</label>
                        <input type="date" class="form-input" x-model="cab.fecha_liquidacion">
                    </div>
                    <div>
                        <label class="form-label">Chofer *</label>
                        <select class="form-input" x-model="cab.chofer_id">
                            <option value="">Seleccionar...</option>
                            @foreach ($choferes as $c)<option value="{{ $c->id }}">{{ $c->alias }} · {{ $c->tipo->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Vehículo</label>
                        <select class="form-input" x-model="cab.vehiculo_id">
                            <option value="">—</option>
                            @foreach ($vehiculos as $id => $placa)<option value="{{ $id }}">{{ $placa }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tipo</label>
                        <select class="form-input" x-model="cab.tipo">
                            @foreach (\App\Enums\TipoChofer::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- 2. Ventas por cliente --}}
            <div class="card">
                <div class="card-header flex-wrap">
                    <div>
                        <p class="card-title">1. Ventas por cliente</p>
                        <p class="text-xs text-slate-500">Busca al cliente; se cargan sus precios. Ingresa balones vendidos, vacíos devueltos y cómo pagó.</p>
                    </div>
                    <div class="w-full max-w-md" x-show="editable">
                        <select x-ref="buscadorCliente" placeholder="🔍 Agregar cliente (código, nombre o dirección)..."></select>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                        <tr><th class="min-w-56">Cliente</th><th>Producto</th><th>Empresa</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Total</th>
                            <th class="text-right">Vacíos dev.</th><th>Pago</th><th>Crédito</th><th></th></tr>
                        </thead>
                        <tbody>
                        <template x-if="!items.length">
                            <tr><td colspan="10" class="py-10 text-center text-sm text-slate-400">Aún no hay ventas. Usa el buscador «Agregar cliente».</td></tr>
                        </template>
                        <template x-for="(item, index) in items" :key="item.uid">
                            <tr :class="esPrimeraFilaCliente(item, index) ? '' : 'bg-slate-50/50'">
                                <td>
                                    <template x-if="esPrimeraFilaCliente(item, index)">
                                        <div>
                                            <p class="font-semibold text-slate-900" x-text="nombreCliente(item.cliente_id)"></p>
                                            <p class="text-[11px] text-slate-400">
                                                <span x-text="'Cód. ' + (clientes[item.cliente_id]?.codigo ?? '')"></span>
                                                <template x-if="clientes[item.cliente_id]?.deuda > 0"><span class="font-semibold text-rose-600" x-text="' · debe ' + money(clientes[item.cliente_id].deuda)"></span></template>
                                            </p>
                                        </div>
                                    </template>
                                    <button type="button" class="mt-1 text-[11px] font-semibold text-brand-600 hover:underline" x-show="editable" @click="agregarProducto(item)">+ otro producto</button>
                                </td>
                                <td>
                                    <select class="form-input w-24 py-1.5" x-model.number="item.producto_id" @change="cambiarProducto(item)">
                                        <template x-for="p in productos" :key="p.id"><option :value="p.id" x-text="p.codigo" :selected="p.id === +item.producto_id"></option></template>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-input w-32 py-1.5" x-model.number="item.empresa_id">
                                        <template x-for="e in empresas" :key="e.id"><option :value="e.id" x-text="e.nombre" :selected="e.id === +item.empresa_id"></option></template>
                                    </select>
                                </td>
                                <td><input type="number" min="1" data-cantidad class="form-input w-20 py-1.5 text-right font-semibold" x-model="item.cantidad"></td>
                                <td><input type="number" min="0" step="0.01" class="form-input w-24 py-1.5 text-right" x-model="item.precio"></td>
                                <td class="text-right font-semibold tabular-nums" x-text="money(totalItem(item))"></td>
                                <td><input type="number" min="0" class="form-input w-20 py-1.5 text-right" x-model="item.vacios_devueltos"></td>
                                <td>
                                    <select class="form-input w-32 py-1.5" x-model="item.metodo_pago">
                                        @foreach (\App\Enums\MetodoPago::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                    </select>
                                    <input type="text" class="form-input mt-1 w-32 py-1 text-xs" placeholder="N° operación" x-show="item.metodo_pago !== 'efectivo'" x-model="item.numero_operacion">
                                </td>
                                <td>
                                    <label class="inline-flex items-center gap-1.5 text-xs font-medium"><input type="checkbox" class="form-check" x-model="item.es_credito" @change="toggleCredito(item)"> Crédito</label>
                                    <input type="number" min="0" step="0.01" class="form-input mt-1 w-24 py-1 text-right text-xs" x-show="item.es_credito" x-model="item.monto_credito" placeholder="Monto">
                                </td>
                                <td><button type="button" class="btn-icon danger" x-show="editable" @click="quitarItem(item)" title="Quitar"><x-heroicon-o-trash class="h-4 w-4"/></button></td>
                            </tr>
                        </template>
                        </tbody>
                        <tfoot x-show="items.length">
                        <tr><td colspan="3">Total vendido</td><td class="text-right" x-text="totalBalones"></td><td></td><td class="text-right" x-text="money(totalVenta)"></td><td class="text-right" x-text="totalVacios"></td><td colspan="3"></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- 3. FISE --}}
            <div class="card">
                <div class="card-header">
                    <div><p class="card-title">2. FISE que dejaron los clientes</p><p class="text-xs text-slate-500">Vales de S/ 20, 30 y 43. Se descuentan del efectivo.</p></div>
                    <x-badge color="violet"><span x-text="cantidadFises + ' vales · ' + money(totalFises)"></span></x-badge>
                </div>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead><tr><th>Cliente</th><template x-for="v in valoresFise" :key="v"><th class="text-right" x-text="'S/ ' + v"></th></template><th class="text-right">Subtotal</th></tr></thead>
                        <tbody>
                        <template x-for="clienteId in clientesDelDia" :key="clienteId">
                            <tr>
                                <td class="font-medium" x-text="nombreCliente(clienteId)"></td>
                                <template x-for="v in valoresFise" :key="v"><td class="text-right"><input type="number" min="0" class="form-input ml-auto w-20 py-1.5 text-right" x-model="fisesDe(clienteId)[v]"></td></template>
                                <td class="text-right font-semibold tabular-nums" x-text="money(subtotalFise(clienteId))"></td>
                            </tr>
                        </template>
                        <tr class="bg-slate-50/60">
                            <td class="text-slate-500">Sin cliente asignado</td>
                            <template x-for="v in valoresFise" :key="v"><td class="text-right"><input type="number" min="0" class="form-input ml-auto w-20 py-1.5 text-right" x-model="fisesDe(null)[v]"></td></template>
                            <td class="text-right font-semibold tabular-nums" x-text="money(subtotalFise('sin'))"></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                {{-- 4. Cobranzas --}}
                <div class="card">
                    <div class="card-header flex-wrap">
                        <div><p class="card-title">3. Cobranzas de deudas anteriores</p><p class="text-xs text-slate-500">Clientes que pagaron créditos al chofer.</p></div>
                        <div class="w-full" x-show="editable"><select x-ref="buscadorCobranza" placeholder="🔍 Cliente que pagó una deuda..."></select></div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <template x-if="!cobranzas.length"><p class="px-5 py-6 text-center text-sm text-slate-400">Sin cobranzas.</p></template>
                        <template x-for="c in cobranzas" :key="c.uid">
                            <div class="grid grid-cols-[1fr_auto] gap-2 px-5 py-3">
                                <div>
                                    <p class="text-sm font-semibold" x-text="nombreCliente(c.cliente_id)"></p>
                                    <p class="text-[11px] text-slate-400" x-text="'Deuda: ' + money(clientes[c.cliente_id]?.deuda)"></p>
                                </div>
                                <button type="button" class="btn-icon danger" x-show="editable" @click="cobranzas.splice(cobranzas.indexOf(c), 1)"><x-heroicon-o-trash class="h-4 w-4"/></button>
                                <div class="col-span-2 grid grid-cols-3 gap-2">
                                    <input type="number" min="0" step="0.01" class="form-input py-1.5 text-right" x-model="c.monto" placeholder="Monto">
                                    <select class="form-input py-1.5" x-model="c.metodo_pago">@foreach (\App\Enums\MetodoPago::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                                    <input type="text" class="form-input py-1.5" x-model="c.numero_operacion" placeholder="N° operación">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- 5. Gastos --}}
                <div class="card">
                    <div class="card-header">
                        <div><p class="card-title">4. Gastos del chofer</p><p class="text-xs text-slate-500">Combustible, peajes, viáticos (sobre todo en ruta).</p></div>
                        <button type="button" class="btn btn-secondary btn-sm" x-show="editable" @click="agregarGasto()"><x-heroicon-o-plus class="h-4 w-4"/> Gasto</button>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <template x-if="!gastos.length"><p class="px-5 py-6 text-center text-sm text-slate-400">Sin gastos.</p></template>
                        <template x-for="g in gastos" :key="g.uid">
                            <div class="grid grid-cols-[1fr_7rem_6rem_auto] items-center gap-2 px-5 py-3">
                                <input type="text" class="form-input py-1.5" x-model="g.concepto" placeholder="Concepto">
                                <input type="number" min="0" step="0.01" class="form-input py-1.5 text-right" x-model="g.monto" placeholder="Monto">
                                <input type="text" class="form-input py-1.5" x-model="g.comprobante" placeholder="Comprob.">
                                <button type="button" class="btn-icon danger" x-show="editable" @click="gastos.splice(gastos.indexOf(g), 1)"><x-heroicon-o-trash class="h-4 w-4"/></button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="card card-body">
                <label class="form-label">Observaciones</label>
                <textarea class="form-input" rows="2" x-model="cab.observaciones"></textarea>
            </div>
        </div>

        {{-- Resumen --}}
        <aside class="space-y-4 2xl:sticky 2xl:top-24 2xl:self-start">
            <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-brand-900 via-brand-950 to-slate-950 text-white shadow-xl">
                <div class="p-6">
                    <p class="text-xs font-bold uppercase tracking-widest text-brand-200">Resumen de liquidación</p>
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-300">Venta total</dt><dd class="font-semibold tabular-nums" x-text="money(totalVenta)"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-300">+ Cobranzas</dt><dd class="tabular-nums text-emerald-300" x-text="money(totalCobranzas)"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-300">− Créditos</dt><dd class="tabular-nums text-rose-300" x-text="money(totalCredito)"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-300">− Vouchers (Yape/Plin/transf.)</dt><dd class="tabular-nums text-rose-300" x-text="money(totalVouchers)"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-300">− FISE</dt><dd class="tabular-nums text-rose-300" x-text="money(totalFises)"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-300">− Gastos</dt><dd class="tabular-nums text-rose-300" x-text="money(totalGastos)"></dd></div>
                    </dl>
                    <div class="mt-5 border-t border-white/10 pt-4">
                        <p class="text-xs font-semibold text-brand-200">Efectivo que debe dejar en caja</p>
                        <p class="mt-1 text-4xl font-extrabold tracking-tight tabular-nums" x-text="money(efectivo)"></p>
                    </div>
                </div>
                <div class="bg-white/5 p-6">
                    <label class="text-xs font-semibold text-brand-200">Efectivo entregado (contado en caja)</label>
                    <input type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-0 bg-white/10 px-3 py-2 text-lg font-bold text-white ring-1 ring-white/20 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-400 focus:outline-none"
                           x-model="cab.efectivo_entregado" placeholder="0.00">
                    <template x-if="diferencia !== null">
                        <p class="mt-2 text-sm font-semibold" :class="diferencia === 0 ? 'text-emerald-300' : (diferencia > 0 ? 'text-sky-300' : 'text-rose-300')"
                           x-text="diferencia === 0 ? '✔ Cuadra exacto' : (diferencia > 0 ? 'Sobran ' : 'Faltan ') + money(Math.abs(diferencia))"></p>
                    </template>
                </div>
            </div>

            <div class="card card-body">
                <p class="mb-3 text-sm font-semibold">Balones vendidos</p>
                <table class="w-full text-sm">
                    <thead><tr class="text-xs text-slate-400"><th class="text-left font-semibold">Prod.</th><th class="text-right font-semibold">Liquidación</th><th class="text-right font-semibold">Logística</th></tr></thead>
                    <tbody>
                    <template x-for="codigo in codigosCuadre" :key="codigo">
                        <tr class="border-t border-slate-100">
                            <td class="py-1.5 font-mono font-bold" x-text="codigo"></td>
                            <td class="py-1.5 text-right tabular-nums" x-text="balones[codigo] ?? 0"></td>
                            <td class="py-1.5 text-right tabular-nums">
                                <span x-text="cuadre[codigo] ?? '—'"></span>
                                <template x-if="cuadre[codigo] !== undefined && cuadre[codigo] !== (balones[codigo] ?? 0)"><span class="ml-1 text-xs font-bold text-rose-600">≠</span></template>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
                <p class="mt-3 text-[11px] text-slate-400">«Logística» = vendidos según los despachos retornados del chofer ese día.</p>
            </div>

            <div class="grid gap-2" x-show="editable">
                <button type="button" class="btn btn-primary w-full py-3" @click="guardar()" :disabled="guardando">
                    <x-heroicon-o-document-check class="h-5 w-5"/> <span x-text="guardando ? 'Guardando...' : 'Guardar borrador'"></span>
                </button>
                <button type="button" class="btn btn-success w-full py-3" @click="guardar('cerrar')" :disabled="guardando">
                    <x-heroicon-o-lock-closed class="h-5 w-5"/> Guardar y cerrar liquidación
                </button>
                <p class="text-center text-[11px] text-slate-400" x-show="sucio">Hay cambios sin guardar</p>
            </div>
        </aside>
    </fieldset>
</div>
</x-layouts.app>
