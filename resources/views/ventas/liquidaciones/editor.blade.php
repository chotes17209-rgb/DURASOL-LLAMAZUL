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
                        <p class="text-[10.5px] font-semibold tracking-[.16em] text-[#b9c7df] uppercase">Hoja de liquidación diaria</p>
                        <p class="doc-num">{{ $liquidacion->exists ? $liquidacion->codigo : 'NUEVA' }}</p>
                    </div>
                    <span class="doc-tag">{{ $liquidacion->estado?->label() ?? 'Borrador' }}</span>
                </div>
                <div class="text-right text-[12px] leading-snug text-[#d4ddec]">
                    <p>Ventas del <b class="text-white" x-text="fechaLarga(cab.fecha_venta)"></b></p>
                    <p>se liquidan el <b class="text-white" x-text="fechaLarga(cab.fecha_liquidacion)"></b></p>
                </div>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="grid gap-3 p-4 sm:grid-cols-3 xl:grid-cols-5">
                    <div>
                        <label class="form-label">Fecha de venta</label>
                        <input type="date" class="form-input" x-model="cab.fecha_venta" max="{{ today()->subDay()->format('Y-m-d') }}">
                    </div>
                    <div>
                        <label class="form-label">Fecha de liquidación</label>
                        <input type="date" class="form-input" x-model="cab.fecha_liquidacion" max="{{ today()->format('Y-m-d') }}">
                    </div>
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
                <div class="border-t border-line bg-panel lg:border-t-0 lg:border-l">
                    <p class="px-4 pt-3 text-[10.5px] font-semibold tracking-[.12em] text-brand-800 uppercase">Stock disponible <span class="font-normal tracking-normal text-slate-500 normal-case">(compras − ventas)</span></p>
                    <table class="mt-1.5 w-full text-[13px]">
                        <thead><tr class="text-[10.5px] tracking-wide text-slate-500 uppercase"><th class="px-4 py-1 text-left font-semibold">Empresa</th><th class="px-2 text-right font-semibold">S10</th><th class="px-2 text-right font-semibold">S45</th><th class="px-4 text-right font-semibold">M10</th></tr></thead>
                        <tbody>
                        <template x-for="e in empresas" :key="e.id">
                            <tr class="border-t border-line-soft">
                                <td class="px-4 py-1.5 font-semibold text-brand-900" x-text="e.nombre"></td>
                                <template x-for="(c, n) in ['S10', 'S45', 'M10']" :key="c">
                                    <td class="py-1.5 text-right tabular-nums" :class="[n === 2 ? 'px-4' : 'px-2', disponible(e, c) < 0 ? 'font-semibold text-red-700' : '']" x-text="disponible(e, c).toLocaleString('es-PE')"></td>
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
            <div class="ledger-cell"><dt>(−) FISE</dt><dd x-text="dec(totalFises)"></dd></div>
            <div class="ledger-cell"><dt>(−) Vouchers y varios</dt><dd x-text="dec(totalGastos + totalVouchers)"></dd></div>
            <div class="ledger-cell ledger-total border-r-0"><dt>Por depositar</dt><dd x-text="'S/ ' + dec(efectivo)"></dd></div>
        </dl>

        {{-- Registro de ventas: una fila por venta, como la hoja REGISTRO --}}
        <section class="card">
            <div class="card-header flex-wrap">
                <div>
                    <p class="card-title">Registro de ventas</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Escribe el <b>código</b> o el <b>nombre</b> del cliente. El precio sale de su lista de precios vigente y no se modifica aquí. <b>Enter</b> baja a la fila siguiente; doble clic en crédito carga todo el importe.</p>
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
                        <th class="min-w-64">Nombre</th>
                        <th class="w-28">Empresa</th>
                        <th class="w-20">Pres.</th>
                        <th class="w-16 text-right">Cant.</th>
                        <th class="w-20 text-right">P. unit.</th>
                        <th class="w-24 text-right">Total</th>
                        <th class="w-16 text-right">Devuelve</th>
                        <th class="w-24 text-right">Crédito</th>
                        <th class="w-24 text-right">Contado</th>
                        <th class="w-28">Forma de pago</th>
                        <th class="w-28">N° operación</th>
                        <th class="w-8"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(item, i) in items" :key="item.uid">
                        <tr :data-fila="item.uid" :class="!item.cliente_id && !item.texto && 'fila-vacia'">
                            <td class="bg-panel text-center text-[11px] text-slate-400" x-text="i + 1"></td>
                            <td class="!p-0"><input class="cell-input text-left font-mono font-semibold text-brand-900" data-col="codigo" inputmode="numeric" x-model="item.codigo" @change="buscarCodigo(item)" @keydown.enter.prevent="$event.target.blur(); siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="!p-0">
                                <div class="flex items-center">
                                    <input class="cell-input text-left" data-col="cliente" autocomplete="off" placeholder="Buscar por nombre..."
                                           :class="item.cliente_id ? 'font-semibold text-slate-900' : ''"
                                           x-model="item.texto" @input="escribirNombre(item, $event, (f, c) => asignarCliente(f, c))"
                                           @keydown="teclaNombre($event)" @blur="cerrarSugerencias()">
                                    <span class="mr-1.5 shrink-0 rounded-sm px-1.5 py-px text-[10px] font-semibold whitespace-nowrap" x-show="item.error || (item.cliente_id && clientes[item.cliente_id]?.deuda > 0)"
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
                            <td class="cell-fija text-right" title="Precio vigente del cliente (se cambia en «Precios de venta»)" x-text="item.precio !== '' ? dec(item.precio) : ''"></td>
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

        <div class="grid gap-4 xl:grid-cols-3">
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
                                <p class="px-1.5 pb-1 text-[10px]" x-show="c.error || c.cliente_id" :class="c.error ? 'font-semibold text-red-700' : 'text-slate-500'"
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
                <p class="px-4 py-2 text-[11px] text-slate-500">Se aplica a las deudas más antiguas del cliente.</p>
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
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            {{-- Cuadre de efectivo, como un comprobante --}}
            <section class="card overflow-hidden">
                <div class="card-header"><p class="card-title">Cuadre de efectivo</p><span class="text-[11px] text-slate-500">Fórmula de la hoja RESUMEN GNRAL</span></div>
                <table class="recibo">
                    <tr><td>Venta total <span class="text-xs text-slate-400" x-text="'(' + totalBalones + ' balones)'"></span></td><td x-text="dec(totalVenta)"></td></tr>
                    <tr><td>(+) Cobranzas de créditos anteriores</td><td x-text="dec(totalCobranzas)"></td></tr>
                    <tr class="resta"><td>(−) Ventas al crédito</td><td x-text="dec(totalCredito)"></td></tr>
                    <tr class="resta"><td>(−) Vales FISE</td><td x-text="dec(totalFises)"></td></tr>
                    <tr class="resta"><td>(−) Varios / gastos del chofer</td><td x-text="dec(totalGastos)"></td></tr>
                    <tr class="resta"><td>(−) Vouchers (Yape, Plin, transferencias)</td><td x-text="dec(totalVouchers)"></td></tr>
                    <tr class="final"><td>POR DEPOSITAR</td><td x-text="'S/ ' + dec(efectivo)"></td></tr>
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
                <p class="px-4 py-2 text-[11px] text-slate-500">«Según parte» = llenos que salieron con el chofer menos los que devolvió, en el parte diario de la fecha de venta.</p>
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

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-white px-6 py-2.5 lg:left-64 no-print" style="box-shadow: 0 -4px 12px -6px rgb(10 26 56 / .15)">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex flex-wrap items-center gap-x-5 text-xs text-slate-500">
                <span>Balones <b class="text-slate-800" x-text="totalBalones"></b></span>
                <span>Venta <b class="text-slate-800" x-text="dec(totalVenta)"></b></span>
                <span>Crédito <b class="text-slate-800" x-text="dec(totalCredito)"></b></span>
                <span class="rounded-sm bg-brand-800 px-2 py-1 text-white">Por depositar <b class="text-[13px]" x-text="'S/ ' + dec(efectivo)"></b></span>
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
