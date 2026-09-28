import TomSelect from 'tom-select';

/**
 * Editor de liquidación diaria.
 *
 * Flujo: fecha → chofer → agregar clientes (cantidad, vacíos, método de pago, crédito)
 * → FISE por cliente → cobranzas y gastos → resumen:
 *   Efectivo = Venta + Cobranzas − Créditos − Vouchers − FISE − Gastos
 */
export default function liquidacionEditor(config) {
    return {
        urls: config.urls,
        productos: config.productos,
        empresas: config.empresas,
        choferes: config.choferes,
        metodos: config.metodos,
        valoresFise: config.valoresFise,
        editable: config.editable,
        cab: config.cabecera,
        items: config.items || [],
        fises: config.fises || {},
        cobranzas: config.cobranzas || [],
        gastos: config.gastos || [],
        clientes: config.clientes || {},
        cuadre: {},
        guardando: false,
        sucio: false,
        empresaDefecto: config.empresas[0]?.id ?? null,

        init() {
            this.$nextTick(() => {
                this.initBuscador(this.$refs.buscadorCliente, (id) => this.agregarCliente(id));
                this.initBuscador(this.$refs.buscadorCobranza, (id) => this.agregarCobranza(id));
            });
            this.cargarCuadre();
            this.$watch('cab.chofer_id', () => {
                this.cambiarChofer();
                this.cargarCuadre();
            });
            this.$watch('cab.fecha_venta', (v) => {
                this.sugerirFechaLiquidacion(v);
                this.cargarCuadre();
            });
            ['items', 'fises', 'cobranzas', 'gastos', 'cab'].forEach((k) => this.$watch(k, () => {
                this.sucio = true;
            }));
            window.addEventListener('beforeunload', (e) => {
                if (this.sucio && this.editable) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        /* ---------------- Buscadores de clientes (Tom Select remoto) ---------------- */
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
                preload: 'focus',
                load(query, callback) {
                    const params = new URLSearchParams({ q: query, chofer_id: self.cab.chofer_id || '' });
                    window.request(`${self.urls.buscarClientes}?${params}`, { json: true })
                        .then((d) => callback(d.results))
                        .catch(() => callback());
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

        async datosCliente(id) {
            if (this.clientes[id]?.precios) return this.clientes[id];
            const params = new URLSearchParams({ cliente_id: id, fecha: this.cab.fecha_venta });
            const data = await window.request(`${this.urls.datosCliente}?${params}`, { json: true });
            this.clientes[id] = data;
            return data;
        },

        /* ---------------- Ventas ---------------- */
        async agregarCliente(id) {
            try {
                const cliente = await this.datosCliente(id);
                this.items.push(this.nuevoItem(id, this.productoSugerido(cliente)));
                this.$nextTick(() => {
                    const inputs = this.$root.querySelectorAll('[data-cantidad]');
                    inputs[inputs.length - 1]?.focus();
                });
                if (cliente.deuda > 0) {
                    window.notify('info', `${cliente.nombre} tiene deuda pendiente de ${this.money(cliente.deuda)}`);
                }
            } catch (e) {
                window.handleRequestError(e);
            }
        },

        productoSugerido(cliente) {
            const principal = this.productos.find((p) => p.codigo === 'S10');
            if (principal && cliente.precios?.[principal.id] !== undefined) return principal.id;
            const conPrecio = this.productos.find((p) => cliente.precios?.[p.id] !== undefined);
            return (conPrecio || principal || this.productos[0]).id;
        },

        nuevoItem(clienteId, productoId) {
            const precio = this.clientes[clienteId]?.precios?.[productoId] ?? '';
            return {
                uid: `${Date.now()}${Math.random()}`,
                cliente_id: clienteId,
                producto_id: productoId,
                empresa_id: this.empresaDefecto,
                cantidad: '',
                precio,
                vacios_devueltos: '',
                metodo_pago: 'efectivo',
                es_credito: false,
                monto_credito: '',
                numero_operacion: '',
                observacion: '',
            };
        },

        agregarProducto(item) {
            const usado = new Set(this.items.filter((i) => i.cliente_id === item.cliente_id).map((i) => +i.producto_id));
            const precios = this.clientes[item.cliente_id]?.precios || {};
            const siguiente = this.productos.find((p) => !usado.has(p.id) && precios[p.id] !== undefined)
                || this.productos.find((p) => !usado.has(p.id))
                || this.productos[0];
            const nuevo = this.nuevoItem(item.cliente_id, siguiente.id);
            nuevo.empresa_id = item.empresa_id;
            this.items.splice(this.items.indexOf(item) + 1, 0, nuevo);
        },

        cambiarProducto(item) {
            const precio = this.clientes[item.cliente_id]?.precios?.[item.producto_id];
            item.precio = precio ?? '';
            if (precio === undefined) window.notify('warning', 'El cliente no tiene precio para este producto; ingrésalo manualmente.');
        },

        async quitarItem(item) {
            if ((+item.cantidad || 0) > 0) {
                const ok = await window.confirmAction({
                    title: '¿Quitar esta venta?',
                    text: this.nombreCliente(item.cliente_id),
                    confirmText: 'Sí, quitar',
                    danger: true,
                    icon: 'warning',
                });
                if (!ok) return;
            }
            this.items.splice(this.items.indexOf(item), 1);
        },

        totalItem(item) {
            return this.round((+item.cantidad || 0) * (+item.precio || 0));
        },
        creditoItem(item) {
            if (!item.es_credito) return 0;
            const total = this.totalItem(item);
            const monto = item.monto_credito === '' || item.monto_credito === null ? total : +item.monto_credito;
            return Math.min(total, monto || total);
        },
        toggleCredito(item) {
            if (item.es_credito && item.monto_credito === '') item.monto_credito = this.totalItem(item) || '';
        },

        nombreCliente(id) {
            return this.clientes[id]?.nombre ?? `Cliente ${id}`;
        },
        esPrimeraFilaCliente(item, index) {
            return index === 0 || this.items[index - 1].cliente_id !== item.cliente_id;
        },
        codigoProducto(id) {
            return this.productos.find((p) => p.id === +id)?.codigo ?? '';
        },

        /* ---------------- FISE por cliente ---------------- */
        get clientesDelDia() {
            return [...new Set(this.items.map((i) => i.cliente_id))];
        },
        fisesDe(clienteId) {
            const key = clienteId ?? 'sin';
            if (!this.fises[key]) this.fises[key] = {};
            return this.fises[key];
        },
        subtotalFise(key) {
            const f = this.fises[key] || {};
            return this.valoresFise.reduce((s, v) => s + (+f[v] || 0) * v, 0);
        },

        /* ---------------- Cobranzas y gastos ---------------- */
        async agregarCobranza(id) {
            try {
                const cliente = await this.datosCliente(id);
                if (!(cliente.deuda > 0)) {
                    window.alertError('Sin deuda', `${cliente.nombre} no tiene deudas pendientes.`);
                    return;
                }
                this.cobranzas.push({ uid: `${Math.random()}`, cliente_id: id, monto: cliente.deuda, metodo_pago: 'efectivo', numero_operacion: '' });
            } catch (e) {
                window.handleRequestError(e);
            }
        },
        agregarGasto() {
            this.gastos.push({ uid: `${Math.random()}`, concepto: '', monto: '', comprobante: '' });
        },

        /* ---------------- Totales (misma fórmula que el servidor) ---------------- */
        get totalVenta() {
            return this.round(this.items.reduce((s, i) => s + this.totalItem(i), 0));
        },
        get totalCredito() {
            return this.round(this.items.reduce((s, i) => s + this.creditoItem(i), 0));
        },
        get totalVouchersVentas() {
            return this.round(this.items.filter((i) => i.metodo_pago !== 'efectivo').reduce((s, i) => s + this.totalItem(i) - this.creditoItem(i), 0));
        },
        get totalCobranzas() {
            return this.round(this.cobranzas.reduce((s, c) => s + (+c.monto || 0), 0));
        },
        get totalCobranzasDigitales() {
            return this.round(this.cobranzas.filter((c) => c.metodo_pago !== 'efectivo').reduce((s, c) => s + (+c.monto || 0), 0));
        },
        get totalVouchers() {
            return this.round(this.totalVouchersVentas + this.totalCobranzasDigitales);
        },
        get totalFises() {
            return this.round(Object.keys(this.fises).reduce((s, k) => s + this.subtotalFise(k), 0));
        },
        get cantidadFises() {
            return Object.values(this.fises).reduce((s, f) => s + this.valoresFise.reduce((t, v) => t + (+f[v] || 0), 0), 0);
        },
        get totalGastos() {
            return this.round(this.gastos.reduce((s, g) => s + (+g.monto || 0), 0));
        },
        get efectivo() {
            return this.round(this.totalVenta + this.totalCobranzas - this.totalCredito - this.totalVouchers - this.totalFises - this.totalGastos);
        },
        get diferencia() {
            if (this.cab.efectivo_entregado === '' || this.cab.efectivo_entregado === null || this.cab.efectivo_entregado === undefined) return null;
            return this.round(+this.cab.efectivo_entregado - this.efectivo);
        },
        get balones() {
            const r = {};
            this.items.forEach((i) => {
                const c = this.codigoProducto(i.producto_id);
                r[c] = (r[c] || 0) + (+i.cantidad || 0);
            });
            return r;
        },
        get totalBalones() {
            return this.items.reduce((s, i) => s + (+i.cantidad || 0), 0);
        },
        get totalVacios() {
            return this.items.reduce((s, i) => s + (+i.vacios_devueltos || 0), 0);
        },
        get codigosCuadre() {
            return [...new Set([...Object.keys(this.cuadre), ...Object.keys(this.balones)])];
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
            if (!this.cab.chofer_id || !this.cab.fecha_venta) {
                this.cuadre = {};
                return;
            }
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
            return {
                ...this.cab,
                efectivo_entregado: this.cab.efectivo_entregado === '' ? null : this.cab.efectivo_entregado,
                items: this.items.map((i) => ({ ...i, cantidad: +i.cantidad || 0, monto_credito: i.es_credito ? this.creditoItem(i) : 0 })),
                fises,
                cobranzas: this.cobranzas,
                gastos: this.gastos.filter((g) => g.concepto || g.monto),
            };
        },

        async guardar(despues = null) {
            if (this.guardando) return;
            if (!this.items.length && !this.cobranzas.length) {
                window.alertError('Liquidación vacía', 'Agrega al menos una venta o una cobranza.');
                return;
            }
            const sinCantidad = this.items.filter((i) => !(+i.cantidad > 0));
            if (sinCantidad.length) {
                window.alertError('Faltan cantidades', `Hay ${sinCantidad.length} venta(s) sin cantidad. Complétalas o quítalas.`);
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
        round(v) {
            return Math.round((v + Number.EPSILON) * 100) / 100;
        },
        money(v) {
            return 'S/ ' + Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
}
