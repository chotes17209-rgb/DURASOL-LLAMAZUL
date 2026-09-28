<x-layouts.app :title="$liquidacion->exists ? 'Liquidación '.$liquidacion->codigo : 'Nueva liquidación'" breadcrumb="Ventas · Registro de liquidación">
    <x-slot:actions>
        <a href="{{ route('liquidaciones.index') }}" class="btn btn-secondary"><x-heroicon-o-arrow-left/> Volver</a>
        @if ($liquidacion->exists)
            <a href="{{ route('liquidaciones.show', [$liquidacion, 'formato' => 'pdf']) }}" class="btn btn-secondary"><x-heroicon-o-document-arrow-down/> PDF</a>
            <button class="btn btn-secondary" data-modal-url="{{ route('liquidaciones.show', $liquidacion) }}" data-modal-size="xl">Ver</button>
        @endif
    </x-slot:actions>

<div x-data="liquidacionEditor(@js($config))" x-cloak class="pb-16">
    @unless ($liquidacion->esEditable())
        <div class="help mb-3 border-amber-300 bg-amber-50 text-amber-900">
            Esta liquidación está <b>{{ $liquidacion->estado->label() }}</b> y no se puede modificar.
            @if (auth()->user()->isAdmin() && $liquidacion->estado === \App\Enums\EstadoLiquidacion::Cerrada) Un administrador puede reabrirla desde «Ver». @endif
        </div>
    @endunless

    <fieldset :disabled="!editable" class="space-y-4">
        {{-- Cabecera y stock disponible --}}
        <div class="grid gap-4 xl:grid-cols-[1fr_24rem]">
            <div class="card">
                <div class="card-header"><p class="card-title">Datos de la liquidación</p></div>
                <div class="grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-5">
                    <div>
                        <label class="form-label">Fecha de venta *</label>
                        <input type="date" class="form-input" x-model="cab.fecha_venta" max="{{ today()->subDay()->format('Y-m-d') }}">
                    </div>
                    <div>
                        <label class="form-label">Fecha de liquidación *</label>
                        <input type="date" class="form-input" x-model="cab.fecha_liquidacion" max="{{ today()->format('Y-m-d') }}">
                    </div>
                    <div>
                        <label class="form-label">Responsable *</label>
                        <select class="form-input" x-model="cab.chofer_id">
                            <option value="">Seleccionar...</option>
                            @foreach ($choferes as $c)<option value="{{ $c->id }}">{{ $c->alias }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Placa</label>
                        <select class="form-input" x-model="cab.vehiculo_id">
                            <option value="">LOCAL</option>
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
            <div class="card">
                <div class="card-header"><p class="card-title">Stock disponible</p><span class="text-[11px] text-slate-500">compras − ventas</span></div>
                <table class="table table-compact table-grid">
                    <thead><tr><th></th><th class="text-right">S10</th><th class="text-right">S45</th><th class="text-right">M10</th></tr></thead>
                    <tbody>
                    <template x-for="e in empresas" :key="e.id">
                        <tr>
                            <td class="font-semibold" x-text="e.nombre"></td>
                            <template x-for="c in ['S10', 'S45', 'M10']" :key="c">
                                <td class="text-right" :class="disponible(e, c) < 0 && 'text-red-700'" x-text="disponible(e, c).toLocaleString('es-PE')"></td>
                            </template>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Registro de ventas: una fila por venta, como la hoja REGISTRO --}}
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <p class="card-title">Registro de ventas</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Escribe el <b>código</b> o el <b>nombre</b> del cliente; el precio sale de su lista de precios y no se modifica aquí. <b>Enter</b> baja a la siguiente fila.</p>
                </div>
                <div class="flex items-center gap-2 no-print" x-show="editable">
                    <button type="button" class="btn btn-secondary btn-sm" @click="agregarFilas(5)"><x-heroicon-o-plus/> Filas</button>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table-compact table-grid">
                    <thead>
                    <tr>
                        <th class="w-8 text-center">#</th>
                        <th class="w-20">Código</th>
                        <th class="min-w-56">Cliente</th>
                        <th class="w-28">Empresa</th>
                        <th class="w-20">Pres.</th>
                        <th class="w-16 text-right">Cant.</th>
                        <th class="w-20 text-right">Precio</th>
                        <th class="w-24 text-right">Total</th>
                        <th class="w-16 text-right">Bal. dev.</th>
                        <th class="w-24 text-right">Crédito</th>
                        <th class="w-24 text-right">Contado</th>
                        <th class="w-28">Pago</th>
                        <th class="w-28">N° operación</th>
                        <th class="w-8"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(item, i) in items" :key="item.uid">
                        <tr :data-fila="item.uid">
                            <td class="text-center text-xs text-slate-400" x-text="i + 1"></td>
                            <td class="!p-0"><input class="cell-input text-left font-mono" data-col="codigo" inputmode="numeric" x-model="item.codigo" @change="buscarCodigo(item)" @keydown.enter.prevent="$event.target.blur(); siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="!p-0">
                                <div class="flex items-center">
                                    <input class="cell-input text-left" data-col="cliente" placeholder="" autocomplete="off"
                                           :class="item.cliente_id ? 'font-medium text-slate-900' : ''"
                                           x-model="item.texto" @input="escribirNombre(item, $event, (f, c) => asignarCliente(f, c))"
                                           @keydown="teclaNombre($event)" @blur="cerrarSugerencias()">
                                    <span class="shrink-0 pr-1.5 text-[10px] whitespace-nowrap" x-show="item.error || (item.cliente_id && clientes[item.cliente_id]?.deuda > 0)"
                                          :class="item.error ? 'font-semibold text-red-700' : 'text-amber-700'"
                                          x-text="item.error || ('debe ' + dec(clientes[item.cliente_id]?.deuda))"></span>
                                </div>
                            </td>
                            <td class="!p-0">
                                <select class="cell-input text-left" x-model.number="item.empresa_id">
                                    <template x-for="e in empresas" :key="e.id"><option :value="e.id" x-text="e.nombre" :selected="e.id === item.empresa_id"></option></template>
                                </select>
                            </td>
                            <td class="!p-0">
                                <select class="cell-input text-left" x-model.number="item.producto_id" @change="cambiarProducto(item)">
                                    <template x-for="p in productos" :key="p.id"><option :value="p.id" x-text="p.codigo" :selected="p.id === item.producto_id"></option></template>
                                </select>
                            </td>
                            <td class="!p-0"><input type="number" min="0" class="cell-input" data-col="cantidad" x-model="item.cantidad" @keydown.enter.prevent="siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="cell-fija text-right" title="Precio vigente del cliente (se cambia en «Precios de venta»)" x-text="item.precio !== '' ? dec(item.precio) : ''"></td>
                            <td class="text-right font-semibold" x-text="totalItem(item) ? dec(totalItem(item)) : ''"></td>
                            <td class="!p-0"><input type="number" min="0" class="cell-input" data-col="vacios" x-model="item.vacios_devueltos" @keydown.enter.prevent="siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="!p-0"><input type="number" min="0" step="0.01" class="cell-input" data-col="credito" x-model="item.monto_credito" @dblclick="todoCredito(item)" title="Doble clic: todo al crédito" @keydown.enter.prevent="siguiente($event, items, item, () => agregarFilas(3))"></td>
                            <td class="text-right" x-text="totalItem(item) ? dec(contadoItem(item)) : ''"></td>
                            <td class="!p-0">
                                <select class="cell-input text-left" x-model="item.metodo_pago">
                                    <template x-for="(label, valor) in metodos" :key="valor"><option :value="valor" x-text="label" :selected="valor === item.metodo_pago"></option></template>
                                </select>
                            </td>
                            <td class="!p-0"><input class="cell-input text-left" x-model="item.numero_operacion" x-show="item.metodo_pago !== 'efectivo'"></td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable" @click="quitarItem(item)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="5">TOTAL <span class="ml-2 text-xs font-normal text-slate-500" x-text="filasConDatos.length + ' venta(s)'"></span></td>
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
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            {{-- Cobranzas --}}
            <div class="card">
                <div class="card-header">
                    <p class="card-title">Cobranzas de créditos</p>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="editable" @click="agregarCobranza()"><x-heroicon-o-plus/></button>
                </div>
                <table class="table table-compact table-grid">
                    <thead><tr><th class="w-20">Código</th><th>Cliente</th><th class="w-24 text-right">Monto</th><th class="w-28">Pago</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    <template x-for="c in cobranzas" :key="c.uid">
                        <tr :data-fila="c.uid">
                            <td class="!p-0"><input class="cell-input text-left font-mono" data-col="codigo" x-model="c.codigo" @change="buscarCodigoCobranza(c)" @keydown.enter.prevent="$event.target.blur()"></td>
                            <td class="!p-0">
                                <input class="cell-input text-left" autocomplete="off" x-model="c.texto" placeholder="Nombre..."
                                       @input="escribirNombre(c, $event, (f, cl) => asignarCobranza(f, cl))" @keydown="teclaNombre($event)" @blur="cerrarSugerencias()">
                                <p class="px-1.5 pb-0.5 text-[10px]" x-show="c.error || c.cliente_id" :class="c.error ? 'font-semibold text-red-700' : 'text-slate-500'"
                                   x-text="c.error || ('Deuda: ' + dec(clientes[c.cliente_id]?.deuda))"></p>
                            </td>
                            <td class="!p-0"><input type="number" min="0" step="0.01" class="cell-input" x-model="c.monto"></td>
                            <td class="!p-0">
                                <select class="cell-input text-left" x-model="c.metodo_pago">
                                    <template x-for="(label, valor) in metodos" :key="valor"><option :value="valor" x-text="label" :selected="valor === c.metodo_pago"></option></template>
                                </select>
                            </td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable" @click="cobranzas.splice(cobranzas.indexOf(c), 1)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot><tr><td colspan="2">TOTAL</td><td class="text-right" x-text="dec(totalCobranzas)"></td><td colspan="2"></td></tr></tfoot>
                </table>
                <p class="px-3 py-2 text-[11px] text-slate-500">Se aplica a las deudas más antiguas del cliente primero.</p>
            </div>

            {{-- FISE --}}
            <div class="card">
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
                            <td class="text-xs" x-text="key === 'sin' ? 'General (sin cliente)' : nombreCliente(+key)"></td>
                            <template x-for="v in valoresFise" :key="v">
                                <td class="!p-0"><input type="number" min="0" class="cell-input" x-model="fises[key][v]"></td>
                            </template>
                            <td class="text-right" x-text="dec(subtotalFise(key))"></td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable && key !== 'sin'" @click="quitarFise(key)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot>
                    <tr><td>TOTAL</td><template x-for="v in valoresFise" :key="v"><td class="text-right" x-text="cantidadFise(v) || ''"></td></template><td class="text-right" x-text="dec(totalFises)"></td><td></td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Varios --}}
            <div class="card">
                <div class="card-header">
                    <p class="card-title">Varios (gastos del chofer)</p>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="editable" @click="agregarGasto()"><x-heroicon-o-plus/></button>
                </div>
                <table class="table table-compact table-grid">
                    <thead><tr><th>Concepto</th><th class="w-24">Comprob.</th><th class="w-24 text-right">Monto</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    <template x-for="g in gastos" :key="g.uid">
                        <tr>
                            <td class="!p-0"><input class="cell-input text-left" x-model="g.concepto" placeholder="Peaje, combustible..."></td>
                            <td class="!p-0"><input class="cell-input text-left" x-model="g.comprobante"></td>
                            <td class="!p-0"><input type="number" min="0" step="0.01" class="cell-input" x-model="g.monto"></td>
                            <td class="!p-0 text-center"><button type="button" class="btn-icon danger" tabindex="-1" x-show="editable" @click="gastos.splice(gastos.indexOf(g), 1)"><x-heroicon-o-x-mark/></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot><tr><td colspan="2">TOTAL</td><td class="text-right" x-text="dec(totalGastos)"></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>

        {{-- Resumen como la hoja RESUMEN GNRAL --}}
        <div class="grid gap-4 xl:grid-cols-[1fr_24rem]">
            <div class="card">
                <div class="card-header"><p class="card-title">Resumen de la liquidación</p></div>
                <div class="table-wrap">
                    <table class="table table-grid">
                        <thead>
                        <tr>
                            <template x-for="p in productos" :key="p.id"><th class="text-right" x-text="p.codigo"></th></template>
                            <th class="text-right">Venta total</th><th class="text-right">Cobranza</th><th class="text-right">Crédito</th><th class="text-right">Varios</th>
                            <th class="text-right">FISE</th><th class="text-right">Vouchers</th><th class="text-right">Por depositar</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <template x-for="p in productos" :key="p.id"><td class="text-right" x-text="cantidadPor(p.codigo) || ''"></td></template>
                            <td class="text-right font-semibold" x-text="dec(totalVenta)"></td>
                            <td class="text-right" x-text="dec(totalCobranzas)"></td>
                            <td class="text-right" x-text="dec(totalCredito)"></td>
                            <td class="text-right" x-text="dec(totalGastos)"></td>
                            <td class="text-right" x-text="dec(totalFises)"></td>
                            <td class="text-right" x-text="dec(totalVouchers)"></td>
                            <td class="text-right text-base font-bold text-brand-800" x-text="dec(efectivo)"></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <p class="px-4 py-2 text-[11px] text-slate-500">Por depositar = venta total + cobranza − crédito − varios − FISE − vouchers (pagos por Yape, Plin o transferencia).</p>
                <div class="grid gap-3 border-t border-line p-4 sm:grid-cols-3">
                    <div>
                        <label class="form-label">Efectivo entregado por el chofer</label>
                        <input type="number" min="0" step="0.01" class="form-input" x-model="cab.efectivo_entregado" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label">Diferencia</label>
                        <p class="flex h-8 items-center rounded border border-line bg-panel px-2.5 font-semibold tabular-nums"
                           :class="diferencia === null ? 'text-slate-400' : (Math.abs(diferencia) < 0.01 ? 'text-emerald-700' : 'text-red-700')"
                           x-text="diferencia === null ? '—' : (Math.abs(diferencia) < 0.01 ? 'Cuadra' : (diferencia > 0 ? 'Sobra ' : 'Falta ') + dec(Math.abs(diferencia)))"></p>
                    </div>
                    <div>
                        <label class="form-label">Observaciones</label>
                        <input class="form-input" x-model="cab.observaciones">
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><p class="card-title">Cuadre con el almacén</p></div>
                <table class="table table-compact table-grid">
                    <thead><tr><th>Pres.</th><th class="text-right">Liquidado</th><th class="text-right">Según parte</th><th></th></tr></thead>
                    <tbody>
                    <template x-for="codigo in codigosCuadre" :key="codigo">
                        <tr>
                            <td x-text="codigo"></td>
                            <td class="text-right" x-text="balones[codigo] ?? 0"></td>
                            <td class="text-right" x-text="cuadre[codigo] ?? '—'"></td>
                            <td class="text-center">
                                <template x-if="cuadre[codigo] !== undefined">
                                    <span :class="cuadre[codigo] === (balones[codigo] ?? 0) ? 'text-emerald-700' : 'font-semibold text-red-700'" x-text="cuadre[codigo] === (balones[codigo] ?? 0) ? 'OK' : 'Revisar'"></span>
                                </template>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!codigosCuadre.length"><td colspan="4" class="py-4 text-center text-slate-400">Sin datos</td></tr>
                    </tbody>
                </table>
                <p class="px-3 py-2 text-[11px] text-slate-500">«Según parte» = salida de llenos − llenos devueltos del chofer en el parte diario de esa fecha.</p>
            </div>
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
            <p class="flex flex-wrap gap-x-5 text-xs text-slate-500">
                <span>Balones <b class="text-slate-800" x-text="totalBalones"></b></span>
                <span>Venta <b class="text-slate-800" x-text="dec(totalVenta)"></b></span>
                <span>Crédito <b class="text-slate-800" x-text="dec(totalCredito)"></b></span>
                <span>FISE <b class="text-slate-800" x-text="dec(totalFises)"></b></span>
                <span>Por depositar <b class="text-[13px] text-brand-800" x-text="money(efectivo)"></b></span>
                <span class="text-amber-700" x-show="sucio && editable">Cambios sin guardar</span>
            </p>
            <div class="flex gap-2" x-show="editable">
                <button type="button" class="btn btn-secondary" @click="guardar()" :disabled="guardando"><span x-text="guardando ? 'Guardando...' : 'Guardar borrador'"></span></button>
                <button type="button" class="btn btn-primary" @click="guardar('cerrar')" :disabled="guardando">Guardar y cerrar</button>
            </div>
        </div>
    </div>
</div>
</x-layouts.app>
