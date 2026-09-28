import TomSelect from 'tom-select';

/**
 * Liquidación diaria, igual que la hoja REGISTRO del Excel:
 * se escribe el CÓDIGO del cliente y se completan su nombre y su precio (como el BUSCARV);
 * luego presentación, cantidad, balones devueltos, crédito y forma de pago.
 *
 *   Contado       = Total − Crédito
 *   Por depositar = Venta total + Cobranza − Crédito − Varios − FISE − Vouchers
 */
let secuencia = 0;
const uid = () => `n${++secuencia}`;

export default function liquidacionEditor(config) {
    const productoPorCodigo = (codigo) => config.productos.find((p) => p.codigo === codigo);

    return {
        urls: config.urls,
        productos: config.productos,
        empresas: config.empresas,
        choferes: config.choferes,
        metodos: config.metodos,
        valoresFise: config.valoresFise,
        stock: config.stock || {},
        editable: config.editable,
        cab: config.cabecera,
        items: [],
        fises: config.fises || {},
        cobranzas: [],
        gastos: config.gastos || [],
        clientes: config.clientes || {},
        cuadre: {},
        guardando: false,
        sucio: false,
        empresaDefecto: config.empresas[0]?.id ?? null,

        init() {
            this.items = (config.items || []).map((i) => ({ ...i, codigo: this.clientes[i.cliente_id]?.codigo ?? '', error: '' }));
            this.cobranzas = (config.cobranzas || []).map((c) => ({ ...c, codigo: this.clientes[c.cliente_id]?.codigo ?? '', error: '' }));
            if (!this.fises.sin) this.fises.sin = {};
            if (this.editable) {
                this.agregarFilas(Math.max(3, 10 - this.items.length));
                if (!this.cobranzas.length) this.agregarCobranza();
                if (!this.gastos.length) this.agregarGasto();
            }

            this.$nextTick(() => {
                this.initBuscador(this.$refs.buscadorCliente, (id) => this.agregarCliente(id));
                this.sucio = false;
            });
            this.cargarCuadre();
            this.$watch('cab.chofer_id', () => { this.cambiarChofer(); this.cargarCuadre(); });
            this.$watch('cab.fecha_venta', (v) => { this.sugerirFechaLiquidacion(v); this.cargarCuadre(); });
            ['items', 'fises', 'cobranzas', 'gastos', 'cab'].forEach((k) => this.$watch(k, () => { this.sucio = true; }));
            window.addEventListener('beforeunload', (e) => {
                if (this.sucio && this.editable) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        /* ---------------- Buscador por nombre (Tom Select remoto) ---------------- */
        initBuscador(el, onPick) {
            if (!el || el.tomselect) return;
            const self = this;
            new TomSelect(el, {
                valueField: 'id',
                labelField: 'text',
                searchField: ['text'],
                maxOptions: 30,
                placeholder: el.getAttribute('placeholder'),
                dropdownParent: 'body',
                load(query, callback) {
                    const params = new URLSearchParams({ q: query, chofer_id: self.cab.chofer_id || '' });
                    window.request(`${self.urls.buscarClientes}?${params}`, { json: true }).then((d) => callback(d.results)).catch(() => callback());
                },
                render: { no_results: () => '<div class="no-results p-2 text-slate-400">Sin resultados</div>' },
                onChange(value) {
                    if (!value) return;
                    onPick(Number(value));
                    this.clear(true);
                    this.clearOptions();
                },
            });
        },

        async datosCliente(params) {
            const cacheado = params.cliente_id ? this.clientes[params.cliente_id] : Object.values(this.clientes).find((c) => String(c.codigo) === String(params.codigo));
            if (cacheado?.precios) return cacheado;
            const q = new URLSearchParams({ ...params, fecha: this.cab.fecha_venta || '' });
            const data = await window.request(`${this.urls.datosCliente}?${q}`, { json: true });
            this.clientes[data.id] = data;
            return data;
        },

        /* ---------------- Hoja de ventas ---------------- */
        filaVacia(empresaId = null) {
            return {
                uid: uid(), codigo: '', cliente_id: null, producto_id: productoPorCodigo('S10')?.id ?? this.productos[0]?.id,
                empresa_id: empresaId ?? this.empresaDefecto, cantidad: '', precio: '', vacios_devueltos: '',
                metodo_pago: 'efectivo', monto_credito: '', numero_operacion: '', observacion: '', error: '',
            };
        },
        agregarFilas(n = 5) {
            const ultima = this.items[this.items.length - 1];
            for (let i = 0; i < n; i++) this.items.push(this.filaVacia(ultima?.empresa_id));
        },

        /** Al escribir el código: trae cliente y precio (BUSCARV). */
        async buscarCodigo(item) {
            item.error = '';
            const codigo = String(item.codigo || '').trim();
            if (!codigo) { item.cliente_id = null; return; }
            try {
                const cliente = await this.datosCliente({ codigo });
                item.cliente_id = cliente.id;
                item.codigo = cliente.codigo;
                if (cliente.precios?.[item.producto_id] === undefined) item.producto_id = this.productoSugerido(cliente);
                item.precio = cliente.precios?.[item.producto_id] ?? '';
                if (cliente.deuda > 0) window.notify('info', `${cliente.nombre} debe ${this.money(cliente.deuda)}`);
            } catch (e) {
                item.cliente_id = null;
                item.precio = '';
                item.error = e.status === 404 ? 'Código no existe' : 'Error';
            }
        },

        /** Desde el buscador por nombre: usa la primera fila vacía. */
        async agregarCliente(id) {
            try {
                const cliente = await this.datosCliente({ cliente_id: id });
                let fila = this.items.find((i) => !i.cliente_id && !i.codigo && !(+i.cantidad));
                if (!fila) { this.agregarFilas(3); fila = this.items.find((i) => !i.cliente_id && !i.codigo); }
                fila.codigo = cliente.codigo;
                fila.cliente_id = cliente.id;
                fila.producto_id = this.productoSugerido(cliente);
                fila.precio = cliente.precios?.[fila.producto_id] ?? '';
                this.$nextTick(() => document.querySelector(`[data-fila="${fila.uid}"] [data-col="cantidad"]`)?.focus());
            } catch (e) {
                window.handleRequestError(e);
            }
        },

        productoSugerido(cliente) {
            const s10 = productoPorCodigo('S10');
            if (s10 && cliente.precios?.[s10.id] !== undefined) return s10.id;
            return (this.productos.find((p) => cliente.precios?.[p.id] !== undefined) || s10 || this.productos[0]).id;
        },

        cambiarProducto(item) {
            if (!item.cliente_id) return;
            const precio = this.clientes[item.cliente_id]?.precios?.[item.producto_id];
            item.precio = precio ?? '';
            if (precio === undefined) window.notify('warning', 'El cliente no tiene precio para esta presentación; escríbelo.');
        },

        async quitarItem(item) {
            if ((+item.cantidad || 0) > 0) {
                const ok = await window.confirmAction({ title: '¿Quitar esta fila?', text: this.nombreCliente(item.cliente_id), confirmText: 'Sí, quitar', danger: true, icon: 'warning' });
                if (!ok) return;
            }
            this.items.splice(this.items.indexOf(item), 1);
        },

        /** Enter: baja a la misma columna de la fila siguiente (agrega filas al final). */
        siguiente(event, lista, item, agregar) {
            const col = event.target.dataset.col;
            const i = lista.indexOf(item);
            if (i >= lista.length - 1) agregar();
            this.$nextTick(() => {
                const sig = lista[i + 1];
                document.querySelector(`[data-fila="${sig?.uid}"] [data-col="${col}"]`)?.focus();
            });
        },

        totalItem(item) { return this.round((+item.cantidad || 0) * (+item.precio || 0)); },
        creditoItem(item) { return Math.min(this.totalItem(item), Math.max(0, +item.monto_credito || 0)); },
        contadoItem(item) { return this.round(this.totalItem(item) - this.creditoItem(item)); },
        todoCredito(item) { item.monto_credito = this.totalItem(item) || ''; },

        nombreCliente(id) { return id ? (this.clientes[id]?.nombre ?? `Cliente ${id}`) : ''; },
        codigoProducto(id) { return this.productos.find((p) => p.id === +id)?.codigo ?? ''; },
        get filasConDatos() { return this.items.filter((i) => i.cliente_id || +i.cantidad > 0); },

        /* ---------------- FISE ---------------- */
        get clientesDelDia() { return [...new Set(this.items.filter((i) => i.cliente_id).map((i) => i.cliente_id))]; },
        get filasFise() { return Object.keys(this.fises); },
        agregarFise(clienteId) {
            if (clienteId && !this.fises[clienteId]) this.fises[clienteId] = {};
        },
        quitarFise(key) { delete this.fises[key]; this.fises = { ...this.fises }; },
        subtotalFise(key) {
            const f = this.fises[key] || {};
            return this.valoresFise.reduce((s, v) => s + (+f[v] || 0) * v, 0);
        },
        cantidadFise(valor) { return Object.values(this.fises).reduce((s, f) => s + (+f[valor] || 0), 0); },

        /* ---------------- Cobranzas y varios ---------------- */
        agregarCobranza() {
            this.cobranzas.push({ uid: uid(), codigo: '', cliente_id: null, monto: '', metodo_pago: 'efectivo', numero_operacion: '', error: '' });
        },
        async buscarCodigoCobranza(c) {
            c.error = '';
            if (!String(c.codigo || '').trim()) { c.cliente_id = null; return; }
            try {
                const cliente = await this.datosCliente({ codigo: String(c.codigo).trim() });
                c.cliente_id = cliente.id;
                if (!(cliente.deuda > 0)) c.error = 'Sin deuda';
                else if (!c.monto) c.monto = cliente.deuda;
            } catch (e) {
                c.cliente_id = null;
                c.error = e.status === 404 ? 'Código no existe' : 'Error';
            }
        },
        agregarGasto() { this.gastos.push({ uid: uid(), concepto: '', monto: '', comprobante: '' }); },

        /* ---------------- Totales (misma fórmula que el servidor) ---------------- */
        get totalVenta() { return this.round(this.items.reduce((s, i) => s + this.totalItem(i), 0)); },
        get totalCredito() { return this.round(this.items.reduce((s, i) => s + this.creditoItem(i), 0)); },
        get totalContado() { return this.round(this.totalVenta - this.totalCredito); },
        get totalVouchersVentas() {
            return this.round(this.items.filter((i) => i.metodo_pago !== 'efectivo').reduce((s, i) => s + this.contadoItem(i), 0));
        },
        get totalCobranzas() { return this.round(this.cobranzas.reduce((s, c) => s + (+c.monto || 0), 0)); },
        get totalCobranzasDigitales() {
            return this.round(this.cobranzas.filter((c) => c.metodo_pago !== 'efectivo').reduce((s, c) => s + (+c.monto || 0), 0));
        },
        get totalVouchers() { return this.round(this.totalVouchersVentas + this.totalCobranzasDigitales); },
        get totalFises() { return this.round(Object.keys(this.fises).reduce((s, k) => s + this.subtotalFise(k), 0)); },
        get totalGastos() { return this.round(this.gastos.reduce((s, g) => s + (+g.monto || 0), 0)); },
        get efectivo() {
            return this.round(this.totalVenta + this.totalCobranzas - this.totalCredito - this.totalVouchers - this.totalFises - this.totalGastos);
        },
        get diferencia() {
            const e = this.cab.efectivo_entregado;
            if (e === '' || e === null || e === undefined) return null;
            return this.round(+e - this.efectivo);
        },
        cantidadPor(codigo) {
            return this.items.filter((i) => this.codigoProducto(i.producto_id) === codigo).reduce((s, i) => s + (+i.cantidad || 0), 0);
        },
        get balones() {
            const r = {};
            this.items.forEach((i) => {
                if (!(+i.cantidad > 0)) return;
                const c = this.codigoProducto(i.producto_id);
                r[c] = (r[c] || 0) + (+i.cantidad || 0);
            });
            return r;
        },
        get totalBalones() { return this.items.reduce((s, i) => s + (+i.cantidad || 0), 0); },
        get totalVacios() { return this.items.reduce((s, i) => s + (+i.vacios_devueltos || 0), 0); },
        get codigosCuadre() { return [...new Set([...Object.keys(this.cuadre), ...Object.keys(this.balones)])]; },
        /** Stock disponible de la empresa después de esta liquidación (cuadro STOCK DISPONIBLE). */
        disponible(empresa, codigo) {
            const base = this.stock[empresa.nombre]?.[codigo] ?? 0;
            const vendido = this.items.filter((i) => +i.empresa_id === empresa.id && this.codigoProducto(i.producto_id) === codigo)
                .reduce((s, i) => s + (+i.cantidad || 0), 0);
            return base - (config.metodo === 'POST' ? vendido : 0);
        },

        /* ---------------- Cabecera ---------------- */
        cambiarChofer() {
            const chofer = this.choferes.find((c) => String(c.id) === String(this.cab.chofer_id));
            if (!chofer) return;
            if (chofer.vehiculo_id) this.cab.vehiculo_id = String(chofer.vehiculo_id);
            this.cab.tipo = chofer.tipo;
        },
        sugerirFechaLiquidacion(fecha) {
            if (!fecha) return;
            const d = new Date(`${fecha}T12:00:00`);
            d.setDate(d.getDate() + (config.diasLiquidacion ?? 1));
            this.cab.fecha_liquidacion = d.toISOString().slice(0, 10);
        },
        async cargarCuadre() {
            if (!this.cab.chofer_id || !this.cab.fecha_venta) { this.cuadre = {}; return; }
            try {
                const params = new URLSearchParams({ chofer_id: this.cab.chofer_id, fecha: this.cab.fecha_venta });
                this.cuadre = await window.request(`${this.urls.cuadre}?${params}`, { json: true });
            } catch (e) {
                this.cuadre = {};
            }
        },

        /* ---------------- Guardar ---------------- */
        payload() {
            const fises = [];
            Object.entries(this.fises).forEach(([cliente, valores]) => {
                this.valoresFise.forEach((v) => {
                    if ((+valores[v] || 0) > 0) fises.push({ cliente_id: cliente === 'sin' ? null : +cliente, valor: v, cantidad: +valores[v] });
                });
            });
            const limpiar = ({ uid: _u, codigo: _c, error: _e, ...resto }) => resto;
            return {
                ...this.cab,
                efectivo_entregado: this.cab.efectivo_entregado === '' ? null : this.cab.efectivo_entregado,
                items: this.filasConDatos.map((i) => ({
                    ...limpiar(i), cantidad: +i.cantidad || 0, es_credito: this.creditoItem(i) > 0, monto_credito: this.creditoItem(i),
                })),
                fises,
                cobranzas: this.cobranzas.filter((c) => c.cliente_id || +c.monto > 0).map(limpiar),
                gastos: this.gastos.filter((g) => g.concepto || g.monto).map(({ uid: _u, ...g }) => g),
            };
        },

        async guardar(despues = null) {
            if (this.guardando) return;
            const filas = this.filasConDatos;
            if (!filas.length && !this.cobranzas.some((c) => c.cliente_id)) {
                window.alertError('Liquidación vacía', 'Escribe al menos una venta o una cobranza.');
                return;
            }
            const sinCliente = filas.filter((i) => !i.cliente_id).length;
            const sinCantidad = filas.filter((i) => !(+i.cantidad > 0)).length;
            if (sinCliente || sinCantidad) {
                window.alertError('Revisa la hoja', [sinCliente && `${sinCliente} fila(s) con cantidad pero sin código de cliente válido.`, sinCantidad && `${sinCantidad} fila(s) sin cantidad.`].filter(Boolean).join(' '));
                return;
            }
            this.guardando = true;
            try {
                const data = await window.request(this.urls.guardar, { method: config.metodo, body: this.payload() });
                this.sucio = false;
                if (despues === 'cerrar' && data.cerrarUrl) sessionStorage.setItem('abrirModal', data.cerrarUrl);
                sessionStorage.setItem('flash', JSON.stringify({ type: 'success', message: data.message }));
                window.location.href = data.redirect;
            } catch (e) {
                window.handleRequestError(e);
            } finally {
                this.guardando = false;
            }
        },

        /* ---------------- Utilidades ---------------- */
        round(v) { return Math.round((v + Number.EPSILON) * 100) / 100; },
        money(v) { return 'S/ ' + this.dec(v); },
        dec(v) { return Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
