<x-layouts.app :title="$liquidacion->exists ? 'Liquidación '.$liquidacion->codigo : 'Nueva liquidación'" breadcrumb="Registro de liquidación">
    <x-slot:actions>
        <a href="{{ route('liquidaciones.index') }}" class="btn btn-secondary"><x-heroicon-o-arrow-left/> Volver</a>
        @if ($liquidacion->exists)
            <a href="{{ route('liquidaciones.show', [$liquidacion, 'formato' => 'pdf']) }}" class="btn btn-secondary"><x-heroicon-o-document-arrow-down/> PDF</a>
            <button class="btn btn-secondary" data-modal-url="{{ route('liquidaciones.show', $liquidacion) }}" data-modal-size="xl"><x-heroicon-o-eye/> Ver</button>
        @endif
    </x-slot:actions>

<div x-data="liquidacionEditor(@js($config))" x-cloak class="space-y-4 pb-16">
    @unless ($liquidacion->esEditable())
        <div class="help border-l-amber-500 bg-amber-50 text-amber-900">
            Esta liquidación está <b>{{ $liquidacion->estado->label() }}</b> y no se puede modificar.
            @if (auth()->user()->isAdmin() && $liquidacion->estado === \App\Enums\EstadoLiquidacion::Cerrada) Un administrador puede reabrirla desde «Ver». @endif
        </div>
    @endunless

    <fieldset :disabled="!editable" class="space-y-4">
        {{-- Cabecera del documento --}}
        <section class="doc-head">
            <div class="doc-band">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-[12px] text-slate-500">Hoja de liquidación diaria</p>
                        <p class="doc-num">{{ $liquidacion->exists ? $liquidacion->codigo : 'NUEVA' }}</p>
                    </div>
                    <span class="doc-tag">{{ $liquidacion->estado?->label() ?? 'Borrador' }}</span>
                </div>
                <div class="text-right text-[12px] leading-snug text-slate-600">
                    <p><span x-text="esRuta ? 'Salida a ruta el' : 'Ventas del'"></span> <b class="text-slate-900" x-text="fechaLarga(cab.fecha_venta)"></b></p>
                    <p><span x-text="esRuta ? 'liquidada al volver, el' : 'se liquidan el'"></span> <b class="text-slate-900" x-text="fechaLarga(cab.fecha_liquidacion)"></b></p>
                </div>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="space-y-4 p-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="form-label">Responsable</label>
                            <select class="form-input font-semibold" x-model="cab.chofer_id">
                                <option value="">Seleccionar...</option>
                                @foreach ($choferes as $c)<option value="{{ $c->id }}">{{ $c->alias }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Placa</label>
                            <select class="form-input font-mono" x-model="cab.vehiculo_id">
                                <option value="">LOCAL</option>
                                @foreach ($vehiculos as $id => $placa)<option value="{{ $id }}">{{ $placa }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Tipo de reparto</label>
                            <select class="form-input" x-model="cab.tipo">
                                @foreach (\App\Enums\TipoChofer::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-3 border-t border-line-soft pt-4 sm:grid-cols-3">
                        <div>
                            <label class="form-label" x-text="esRuta ? 'Fecha de atención (salida a ruta)' : 'Fecha de venta'"></label>
                            <input type="date" class="form-input" x-model="cab.fecha_venta" max="{{ today()->format('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="form-label" x-text="esRuta ? 'Fecha de venta / liquidación' : 'Fecha de liquidación'"></label>
                            <input type="date" class="form-input" x-model="cab.fecha_liquidacion" max="{{ today()->format('Y-m-d') }}">
                        </div>
                        <p class="self-end rounded border border-line-soft bg-panel px-3 py-2 text-[12px] leading-snug text-slate-600">
                            <template x-if="esRuta"><span><b class="text-slate-900">Ruta:</b> salida en la fecha de atención y liquidación al retorno (ej.: salida 26/09, liquidación 28/09).</span></template>
                            <template x-if="!esRuta"><span><b class="text-slate-900">Reparto local:</b> se liquida al día siguiente de la venta o el mismo día.</span></template>
                        </p>
                    </div>
                </div>
                <div class="border-t border-line bg-panel lg:border-t-0 lg:border-l">
                    <p class="border-b border-line px-4 py-2 text-[13px] font-semibold text-slate-900">Stock en almacén <span class="font-normal text-slate-500" x-text="'al ' + stock.fecha"></span></p>
                    <table class="mt-1 w-full text-[13px]">
                        <thead><tr class="text-[12px] text-slate-500"><th class="px-4 py-1 text-left font-semibold"></th><th class="px-2 text-right font-semibold">S-10</th><th class="px-2 text-right font-semibold">S-45</th><th class="px-4 text-right font-semibold">M-10</th></tr></thead>
                        <tbody>
                        <template x-for="fila in stock.filas" :key="fila[0]">
                            <tr class="border-t border-line-soft" :class="fila[0] === 'Total' && 'bg-brand-50 font-semibold'">
                                <td class="px-4 py-1.5 font-semibold" :class="fila[0] === 'Total' ? 'text-brand-900' : 'text-slate-700'" x-text="fila[0]"></td>
                                <template x-for="(v, n) in fila.slice(1)" :key="n">
                                    <td class="py-1.5 text-right tabular-nums" :class="[n === 2 ? 'px-4' : 'px-2', fila[0] === 'Total' ? 'font-semibold' : '']" x-text="v === null ? '' : Number(v).toLocaleString('es-PE')"></td>
                                </template>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Totales en vivo (hoja RESUMEN GNRAL) --}}
        <dl class="ledger">
            <div class="ledger-cell"><dt>Balones</dt><dd x-text="totalBalones.toLocaleString('es-PE')"></dd></div>
            <div class="ledger-cell"><dt>Venta total</dt><dd x-text="dec(totalVenta)"></dd></div>
            <div class="ledger-cell"><dt>(+) Cobranza</dt><dd x-text="dec(totalCobranzas)"></dd></div>
            <div class="ledger-cell"><dt>(−) Crédito</dt><dd x-text="dec(totalCredito)"></dd></div>
            <div class="ledger-cell"><dt>(−) FISE, vouchers y varios</dt><dd x-text="dec(totalFises + totalGastos + totalVouchers)"></dd></div>
            <div class="ledger-cell"><dt>Por depositar</dt><dd x-text="dec(efectivo)"></dd></div>
            <div class="ledger-cell ledger-total border-r-0"><dt>Efectivo a entregar</dt><dd x-text="'S/ ' + dec(efectivoAEntregar)"></dd></div>
        </dl>

        {{-- Registro de ventas: una fila por venta, como la hoja REGISTRO --}}
        <section class="card">
            <div class="card-header flex-wrap">
                <div>
                    <p class="card-title">Registro de ventas</p>
                    <p class="mt-0.5 text-[12px] text-slate-500">Cliente por código o nombre (solo clientes del responsable). Precio según lista vigente; clic en el precio para modificarlo. Doble clic en crédito: importe total.</p>
                </div>
                <div class="flex items-center gap-2 no-print" x-show="editable">
                    <span class="text-xs text-slate-500" x-text="filasConDatos.length + ' venta(s)'"></span>
                    <button type="button" class="btn btn-secondary btn-sm" @click="agregarFilas(5)"><x-heroicon-o-plus/> Agregar filas</button>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table-compact table-grid">
                    <thead>
                    <tr class="th-group">
                        <th></th>
                        <th colspan="2">Cliente</th>
                        <th colspan="5">Venta</th>
                        <th>Envases</th>
                        <th colspan="4">Cobro</th>
                        <th></th>
                    </tr>
                    <tr>
                        <th class="w-8 text-center">N°</th>
                        <th class="w-20">Código</th>
                        <th class="min-w-56">Nombre</th>
                        <th class="w-28 min-w-28">Empresa</th>
                        <th class="w-20 min-w-20">Pres.</th>
                        <th class="w-16 text-right">Cant.</th>
                        <th class="w-20 text-right">P. unit.</th>
                        <th class="w-24 text-right">Total</th>
                        <th class="w-16 text-right">Devuelve</th>
                        <th class="w-24 text-right">Crédito</th>
                        <th class="w-24 text-right">Contado</th>
                        <th class="w-28 min-w-28">Forma de pago</th>
                        <th class="w-28">N° operación</th>
                        <th class="w-8"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(item, i) in items" :key="item.uid">
                        <tr :data-fila="item.uid" :class="!item.cliente_id && !item.texto && 'fila-vacia'">
                            <td class="bg-panel text-center text-[12px] text-slate-400" x-text="i + 1"></td>
                            <td class="!p-0"><input class="cell-input text-left font-mono font-semibold text-brand-900" data-col="codigo" inputmode="numeric" x-model="item.codigo" @change="buscarCodigo(item)" @keydown.enter.prevent="$event.target.blur(); siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="!p-0">
                                <div class="flex items-center">
                                    <input class="cell-input text-left" data-col="cliente" autocomplete="off" placeholder="Buscar por nombre..."
                                           :class="item.cliente_id ? 'font-semibold text-slate-900' : ''"
                                           x-model="item.texto" @input="escribirNombre(item, $event, (f, c) => asignarCliente(f, c))"
                                           @keydown="teclaNombre($event)" @blur="cerrarSugerencias()">
                                    <span class="mr-1.5 shrink-0 rounded-md px-1.5 py-px text-[11.5px] font-semibold whitespace-nowrap" x-show="item.error || (item.cliente_id && clientes[item.cliente_id]?.deuda > 0)"
                                          :class="item.error ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'"
                                          x-text="item.error || ('Debe ' + dec(clientes[item.cliente_id]?.deuda))"></span>
                                </div>
                            </td>
                            <td class="!p-0">
                                <select class="cell-input text-left" x-model.number="item.empresa_id">
                                    <template x-for="e in empresas" :key="e.id"><option :value="e.id" x-text="e.nombre" :selected="e.id === item.empresa_id"></option></template>
                                </select>
                            </td>
                            <td class="!p-0">
                                <select class="cell-input text-left font-semibold" x-model.number="item.producto_id" @change="cambiarProducto(item)">
                                    <template x-for="p in productos" :key="p.id"><option :value="p.id" x-text="p.codigo" :selected="p.id === item.producto_id"></option></template>
                                </select>
                            </td>
                            <td class="!p-0"><input type="number" min="0" class="cell-input font-semibold" data-col="cantidad" x-model="item.cantidad" @keydown.enter.prevent="siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="cell-fija !p-0 text-right">
                                <button type="button" tabindex="-1" class="group flex h-7 w-full items-center justify-end gap-1 px-1.5 tabular-nums disabled:cursor-default"
                                        :disabled="!item.cliente_id || !urls.editarPrecio || !editable" @click="editarPrecio(item)"
                                        :title="item.cliente_id && urls.editarPrecio ? 'Modificar la lista de precios del cliente' : 'Precio vigente del cliente'">
                                    <x-heroicon-o-pencil-square class="h-3.5 w-3.5 text-slate-300 group-enabled:group-hover:text-brand-700" x-show="item.cliente_id && urls.editarPrecio && editable"/>
                                    <span x-text="item.precio !== '' ? dec(item.precio) : ''"></span>
                                </button>
                            </td>
                            <td class="text-right font-semibold text-brand-950" x-text="totalItem(item) ? dec(totalItem(item)) : ''"></td>
                            <td class="!p-0"><input type="number" min="0" class="cell-input" data-col="vacios" x-model="item.vacios_devueltos" @keydown.enter.prevent="siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="!p-0"><input type="number" min="0" step="0.01" class="cell-input text-red-700" data-col="credito" x-model="item.monto_credito" @dblclick="todoCredito(item)" title="Doble clic: todo al crédito" @keydown.enter.prevent="siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="text-right" x-text="totalItem(item) ? dec(contadoItem(item)) : ''"></td>
                            <td class="!p-0">
                                <select class="cell-input text-left" x-model="item.metodo_pago">
                                    <template x-for="(label, valor) in metodos" :key="valor"><option :value="valor" x-text="label" :selected="valor === item.metodo_pago"></option></template>
                                </select>
                            </td>
                            <td class="!p-0"><input class="cell-input text-left" x-model="item.numero_operacion" x-show="item.metodo_pago !== 'efectivo'" placeholder="N° voucher"></td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable" @click="quitarItem(item)" title="Quitar fila"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="5">TOTALES</td>
                        <td class="text-right" x-text="totalBalones"></td>
                        <td></td>
                        <td class="text-right" x-text="dec(totalVenta)"></td>
                        <td class="text-right" x-text="totalVacios || ''"></td>
                        <td class="text-right" x-text="dec(totalCredito)"></td>
                        <td class="text-right" x-text="dec(totalContado)"></td>
                        <td colspan="3"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="grid gap-4 min-[1150px]:grid-cols-2">
            {{-- Cobranzas --}}
            <section class="card">
                <div class="card-header">
                    <p class="card-title">Cobranzas de créditos</p>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="editable" @click="agregarCobranza()"><x-heroicon-o-plus/> Agregar</button>
                </div>
                <table class="table table-compact table-grid">
                    <thead><tr><th class="w-20">Código</th><th>Cliente</th><th class="w-24 text-right">Monto</th><th class="w-28">Pago</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    <template x-for="c in cobranzas" :key="c.uid">
                        <tr :data-fila="c.uid">
                            <td class="!p-0"><input class="cell-input text-left font-mono font-semibold text-brand-900" data-col="codigo" x-model="c.codigo" @change="buscarCodigoCobranza(c)" @keydown.enter.prevent="$event.target.blur()"></td>
                            <td class="!p-0">
                                <input class="cell-input text-left" autocomplete="off" x-model="c.texto" placeholder="Buscar por nombre..."
                                       :class="c.cliente_id ? 'font-semibold text-slate-900' : ''"
                                       @input="escribirNombre(c, $event, (f, cl) => asignarCobranza(f, cl))" @keydown="teclaNombre($event)" @blur="cerrarSugerencias()">
                                <p class="px-1.5 pb-1 text-[11.5px]" x-show="c.error || c.cliente_id" :class="c.error ? 'font-semibold text-red-700' : 'text-slate-500'"
                                   x-text="c.error || ('Deuda pendiente: S/ ' + dec(clientes[c.cliente_id]?.deuda))"></p>
                            </td>
                            <td class="!p-0"><input type="number" min="0" step="0.01" class="cell-input font-semibold" x-model="c.monto"></td>
                            <td class="!p-0">
                                <select class="cell-input text-left" x-model="c.metodo_pago">
                                    <template x-for="(label, valor) in metodos" :key="valor"><option :value="valor" x-text="label" :selected="valor === c.metodo_pago"></option></template>
                                </select>
                            </td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable" @click="cobranzas.splice(cobranzas.indexOf(c), 1)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot><tr><td colspan="2">TOTAL COBRADO</td><td class="text-right" x-text="dec(totalCobranzas)"></td><td colspan="2"></td></tr></tfoot>
                </table>
                <p class="px-4 py-2 text-[12px] text-slate-500">Se aplica a las deudas más antiguas del cliente.</p>
            </section>

            {{-- FISE --}}
            <section class="card">
                <div class="card-header">
                    <p class="card-title">Vales FISE</p>
                    <select class="form-input h-7 w-44 text-xs" x-show="editable" @change="agregarFise($event.target.value); $event.target.value = ''">
                        <option value="">+ FISE de un cliente</option>
                        <template x-for="id in clientesDelDia" :key="id"><option :value="id" x-text="nombreCliente(id)"></option></template>
                    </select>
                </div>
                <table class="table table-compact table-grid">
                    <thead><tr><th>Cliente</th><template x-for="v in valoresFise" :key="v"><th class="w-16 text-right" x-text="'S/ ' + v"></th></template><th class="w-20 text-right">Importe</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    <template x-for="key in filasFise" :key="key">
                        <tr>
                            <td class="text-xs font-medium" x-text="key === 'sin' ? 'General (sin cliente)' : nombreCliente(+key)"></td>
                            <template x-for="v in valoresFise" :key="v">
                                <td class="!p-0"><input type="number" min="0" class="cell-input" x-model="fises[key][v]"></td>
                            </template>
                            <td class="text-right font-semibold" x-text="dec(subtotalFise(key))"></td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable && key !== 'sin'" @click="quitarFise(key)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot>
                    <tr><td>TOTAL</td><template x-for="v in valoresFise" :key="v"><td class="text-right" x-text="cantidadFise(v) || ''"></td></template><td class="text-right" x-text="dec(totalFises)"></td><td></td></tr>
                    </tfoot>
                </table>
            </section>

            {{-- Varios --}}
            <section class="card">
                <div class="card-header">
                    <p class="card-title">Varios (gastos del chofer)</p>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="editable" @click="agregarGasto()"><x-heroicon-o-plus/> Agregar</button>
                </div>
                <table class="table table-compact table-grid">
                    <thead><tr><th>Concepto</th><th class="w-24">Comprobante</th><th class="w-24 text-right">Monto</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    <template x-for="g in gastos" :key="g.uid">
                        <tr>
                            <td class="!p-0"><input class="cell-input text-left" x-model="g.concepto" placeholder="Peaje, combustible..."></td>
                            <td class="!p-0"><input class="cell-input text-left" x-model="g.comprobante"></td>
                            <td class="!p-0"><input type="number" min="0" step="0.01" class="cell-input font-semibold" x-model="g.monto"></td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable" @click="gastos.splice(gastos.indexOf(g), 1)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot><tr><td colspan="2">TOTAL</td><td class="text-right" x-text="dec(totalGastos)"></td><td></td></tr></tfoot>
                </table>
            </section>

            {{-- Depósitos: lo que el chofer depositó o transfirió; no siempre se sabe quién lo hizo --}}
            <section class="card">
                <div class="card-header">
                    <div>
                        <p class="card-title">Depósitos (−)</p>
                        <p class="mt-0.5 text-[12px] text-slate-500">Cuenta o medio de depósito (BCP - Durasol, Yape, etc.).</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="editable" @click="agregarDeposito()"><x-heroicon-o-plus/> Agregar</button>
                </div>
                <datalist id="destinos-deposito"><template x-for="d in destinosDeposito" :key="d"><option :value="d"></option></template></datalist>
                <table class="table table-compact table-grid">
                    <thead><tr><th class="w-8 text-center">N°</th><th>Cuenta / destino</th><th class="w-32">N° operación</th><th class="w-28 text-right">Monto</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    <template x-for="(d, n) in depositos" :key="d.uid">
                        <tr>
                            <td class="text-center text-xs text-slate-400" x-text="n + 1"></td>
                            <td class="!p-0"><input class="cell-input text-left font-medium uppercase" list="destinos-deposito" x-model="d.destino" placeholder="BCP - DURASOL, YAPE..."
                                                    @keydown.enter.prevent="d === depositos[depositos.length - 1] && agregarDeposito(); $nextTick(() => $el.closest('tr').nextElementSibling?.querySelector('input')?.focus())"></td>
                            <td class="!p-0"><input class="cell-input text-left" x-model="d.numero_operacion" placeholder="Opcional"></td>
                            <td class="!p-0"><input type="number" min="0" step="0.01" class="cell-input font-semibold" x-model="d.monto"></td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable" @click="depositos.splice(depositos.indexOf(d), 1)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot><tr><td colspan="3">TOTAL DEPOSITADO</td><td class="text-right" x-text="dec(totalDepositos)"></td><td></td></tr></tfoot>
                </table>
            </section>
        </div>

        <div class="grid gap-4 min-[1150px]:grid-cols-2">
            {{-- Cuadre de efectivo, como un comprobante --}}
            <section class="card overflow-hidden">
                <div class="card-header"><p class="card-title">Cuadre de efectivo</p><span class="text-[12px] text-slate-500">Fórmula de la hoja RESUMEN GNRAL</span></div>
                <table class="recibo">
                    <tr><td>Venta total <span class="text-xs text-slate-400" x-text="'(' + totalBalones + ' balones)'"></span></td><td x-text="dec(totalVenta)"></td></tr>
                    <tr><td>(+) Cobranzas de créditos anteriores</td><td x-text="dec(totalCobranzas)"></td></tr>
                    <tr class="resta"><td>(−) Ventas al crédito</td><td x-text="dec(totalCredito)"></td></tr>
                    <tr class="resta"><td>(−) Vales FISE</td><td x-text="dec(totalFises)"></td></tr>
                    <tr class="resta"><td>(−) Varios / gastos del chofer</td><td x-text="dec(totalGastos)"></td></tr>
                    <tr class="resta"><td>(−) Vouchers (Yape, Plin, transferencias)</td><td x-text="dec(totalVouchers)"></td></tr>
                    <tr class="subtotal"><td>Por depositar</td><td x-text="dec(efectivo)"></td></tr>
                    <tr class="resta"><td>(−) Depósitos <span class="text-xs text-slate-400" x-text="depositos.filter(d => +d.monto > 0).length ? '(' + depositos.filter(d => +d.monto > 0).map(d => (d.destino || 'sin destino').toUpperCase()).join(', ') + ')' : ''"></span></td><td x-text="dec(totalDepositos)"></td></tr>
                    <tr class="final"><td>EFECTIVO A ENTREGAR</td><td x-text="'S/ ' + dec(efectivoAEntregar)"></td></tr>
                </table>
                <div class="grid gap-3 p-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label">Efectivo entregado por el chofer</label>
                        <input type="number" min="0" step="0.01" class="form-input h-9 text-[15px] font-semibold" x-model="cab.efectivo_entregado" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label">Diferencia</label>
                        <p class="flex h-9 items-center rounded border px-3 text-[15px] font-semibold tabular-nums"
                           :class="diferencia === null ? 'border-line bg-panel text-slate-400' : (Math.abs(diferencia) < 0.01 ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-red-300 bg-red-50 text-red-700')"
                           x-text="diferencia === null ? 'Sin registrar' : (Math.abs(diferencia) < 0.01 ? 'Cuadra' : (diferencia > 0 ? 'Sobra S/ ' : 'Falta S/ ') + dec(Math.abs(diferencia)))"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Observaciones</label>
                        <input class="form-input" x-model="cab.observaciones" placeholder="Opcional">
                    </div>
                </div>
            </section>

            {{-- Cuadre con almacén y detalle por presentación --}}
            <section class="card">
                <div class="card-header"><p class="card-title">Cuadre con el parte de almacén</p></div>
                <table class="table table-compact table-grid">
                    <thead><tr><th>Presentación</th><th class="text-right">Liquidado</th><th class="text-right">Según parte</th><th class="text-center">Estado</th></tr></thead>
                    <tbody>
                    <template x-for="codigo in codigosCuadre" :key="codigo">
                        <tr>
                            <td class="font-semibold" x-text="codigo"></td>
                            <td class="text-right" x-text="balones[codigo] ?? 0"></td>
                            <td class="text-right" x-text="cuadre[codigo] ?? '—'"></td>
                            <td class="text-center">
                                <span x-show="cuadre[codigo] === undefined" class="badge badge-slate">Sin parte</span>
                                <span x-show="cuadre[codigo] !== undefined && cuadre[codigo] === (balones[codigo] ?? 0)" class="badge badge-green">Cuadra</span>
                                <span x-show="cuadre[codigo] !== undefined && cuadre[codigo] !== (balones[codigo] ?? 0)" class="badge badge-red">Revisar</span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!codigosCuadre.length"><td colspan="4" class="py-6 text-center text-slate-400">Selecciona el responsable y registra las ventas.</td></tr>
                    </tbody>
                </table>
                <p class="px-4 py-2 text-[12px] text-slate-500">«Según parte» = llenos que salieron con el chofer menos los que devolvió, en el parte diario de la fecha de venta.</p>
            </section>
        </div>
    </fieldset>

    {{-- Sugerencias de clientes al escribir el nombre --}}
    <template x-if="sugerencias">
        <div class="sugerencias" data-sugerencias :style="`top:${sugerencias.top}px;left:${sugerencias.left}px;width:${sugerencias.width}px`">
            <p class="px-3 py-2 text-xs text-slate-500" x-show="sugerencias.cargando">Buscando...</p>
            <p class="px-3 py-2 text-xs text-slate-500" x-show="!sugerencias.cargando && !sugerencias.lista.length">Sin coincidencias</p>
            <template x-for="(op, n) in sugerencias.lista" :key="op.id">
                <button type="button" :class="n === sugerencias.indice && 'activa'" @mousedown.prevent="elegirSugerencia(op)" @mouseenter="sugerencias.indice = n">
                    <span x-text="op.text"></span>
                </button>
            </template>
        </div>
    </template>

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-white px-6 py-2.5 lg:left-60 no-print" >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex flex-wrap items-center gap-x-5 text-xs text-slate-500">
                <span>Balones <b class="text-slate-800" x-text="totalBalones"></b></span>
                <span>Venta <b class="text-slate-800" x-text="dec(totalVenta)"></b></span>
                <span>Crédito <b class="text-slate-800" x-text="dec(totalCredito)"></b></span>
                <span>Por depositar <b class="text-slate-800" x-text="dec(efectivo)"></b></span>
                <span>Depósitos <b class="text-slate-800" x-text="dec(totalDepositos)"></b></span>
                <span class="rounded-md border border-brand-200 bg-brand-50 px-2 py-1 text-brand-900">Efectivo a entregar <b class="text-[13px]" x-text="'S/ ' + dec(efectivoAEntregar)"></b></span>
                <span class="text-amber-700" x-show="sucio && editable">● Cambios sin guardar</span>
            </p>
            <div class="flex gap-2" x-show="editable">
                <button type="button" class="btn btn-secondary" @click="guardar()" :disabled="guardando"><span x-text="guardando ? 'Guardando...' : 'Guardar borrador'"></span></button>
                <button type="button" class="btn btn-primary" @click="guardar('cerrar')" :disabled="guardando"><x-heroicon-o-lock-closed/> Guardar y cerrar</button>
            </div>
        </div>
    </div>
</div>
</x-layouts.app>
