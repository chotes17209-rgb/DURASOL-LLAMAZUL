/**
 * Parte diario de almacén: cuatro tablas (ingreso/salida de llenos y vacíos)
 * y el control de stock en vivo: final = inicial + ingresos − salidas.
 */
const CAMPOS = ['s10', 's45', 'm10', 'cambio_s10', 'cambio_s45', 'cambio_m10', 'color_s10', 'color_s45'];

const STOCK = {
    lleno_s10: ['lleno', 's10'],
    lleno_s45: ['lleno', 's45'],
    lleno_m10: ['lleno', 'm10'],
    cambio_s10: ['lleno', 'cambio_s10'],
    cambio_s45: ['lleno', 'cambio_s45'],
    cambio_m10: ['lleno', 'cambio_m10'],
    plomo_s10: ['vacio', 's10'],
    plomo_s45: ['vacio', 's45'],
    color_s10: ['vacio', 'color_s10'],
    color_s45: ['vacio', 'color_s45'],
};

let secuencia = 0;

export default function parteEditor(config) {
    const vacia = (bloque) => ({
        uid: ++secuencia, bloque, placa: '', responsable: '', lugar: '', empresa_id: '', instalacion_id: '', numero_guia: '',
        s10: '', s45: '', m10: '', cambio_s10: '', cambio_s45: '', cambio_m10: '', color_s10: '', color_s45: '', observacion: '',
    });

    const bloques = { lleno_ingreso: [], lleno_salida: [], vacio_ingreso: [], vacio_salida: [] };
    (config.filas || []).forEach((f) => {
        const fila = { ...vacia(f.bloque), ...f };
        CAMPOS.forEach((c) => { fila[c] = +fila[c] ? +fila[c] : ''; });
        fila.empresa_id = fila.empresa_id ?? '';
        fila.instalacion_id = fila.instalacion_id ?? '';
        bloques[f.bloque].push(fila);
    });
    // Siempre algunas filas libres para escribir, como en la hoja.
    Object.keys(bloques).forEach((b) => {
        if (!config.editable) return;
        const libres = Math.max(3, 8 - bloques[b].length);
        for (let i = 0; i < libres; i++) bloques[b].push(vacia(b));
    });

    return {
        tab: 'llenos',
        editable: config.editable,
        inicial: config.inicial,
        instalaciones: config.instalaciones,
        bloques,
        observaciones: config.observaciones || '',
        guardando: false,
        sucio: false,

        init() {
            this.$watch('bloques', () => { this.sucio = true; });
            this.$watch('observaciones', () => { this.sucio = true; });
            window.addEventListener('beforeunload', (e) => {
                if (this.sucio && this.editable) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        agregar(bloque, cantidad = 5) {
            for (let i = 0; i < cantidad; i++) this.bloques[bloque].push(vacia(bloque));
        },
        quitar(bloque, fila) {
            this.bloques[bloque].splice(this.bloques[bloque].indexOf(fila), 1);
        },
        /** Enter en la última fila agrega más filas. */
        siguienteFila(bloque, fila, event) {
            const filas = this.bloques[bloque];
            if (filas.indexOf(fila) >= filas.length - 1) this.agregar(bloque, 3);
            const celdas = [...event.target.closest('table').querySelectorAll('input:not([type=hidden]), select')];
            const columna = event.target.closest('td').cellIndex;
            this.$nextTick(() => {
                const siguiente = celdas.find((el) => el.closest('tr').rowIndex === event.target.closest('tr').rowIndex + 1 && el.closest('td').cellIndex === columna);
                siguiente?.focus();
            });
        },

        /** Al escribir la placa de un camión de planta, propone empresa e instalación. */
        completarPorPlaca(fila) {
            const placa = (fila.placa || '').trim().toUpperCase();
            const inst = this.instalaciones.find((i) => i.placas.includes(placa));
            if (inst && !fila.instalacion_id) {
                fila.instalacion_id = inst.id;
                fila.empresa_id = inst.empresa_id;
            }
        },
        elegirInstalacion(fila) {
            const inst = this.instalaciones.find((i) => String(i.id) === String(fila.instalacion_id));
            if (inst) fila.empresa_id = inst.empresa_id;
        },

        totalFila(fila, columnas) {
            return columnas.reduce((s, c) => s + (+fila[c] || 0), 0);
        },
        totalColumna(bloque, columna) {
            return this.bloques[bloque].reduce((s, f) => s + (+f[columna] || 0), 0);
        },
        totalBloque(bloque, columnas) {
            return columnas.reduce((s, c) => s + this.totalColumna(bloque, c), 0);
        },

        /** TOTAL por presentación (llenos + cambios), como el cuadro TOTAL de la hoja. */
        totalPresentacion(p) {
            return this.control(`lleno_${p}`).final + this.control(`cambio_${p}`).final;
        },

        /** TOTAL de vacíos por presentación (plomos + colores). */
        totalVacios(p) {
            return this.control(`plomo_${p}`).final + this.control(`color_${p}`).final;
        },

        /** Control de stock en vivo. */
        control(llave) {
            const [tipo, columna] = STOCK[llave];
            const ingreso = this.totalColumna(`${tipo}_ingreso`, columna);
            const salida = this.totalColumna(`${tipo}_salida`, columna);
            const inicial = this.inicial[llave] || 0;
            return { inicial, ingreso, salida, final: inicial + ingreso - salida };
        },

        async guardar() {
            if (this.guardando) return;
            const filas = [];
            Object.entries(this.bloques).forEach(([bloque, lista]) => {
                lista.forEach((f) => {
                    if (CAMPOS.some((c) => (+f[c] || 0) > 0)) {
                        const { uid, ...datos } = f;
                        CAMPOS.forEach((c) => { datos[c] = +datos[c] || 0; });
                        datos.empresa_id = datos.empresa_id || null;
                        datos.instalacion_id = datos.instalacion_id || null;
                        filas.push({ ...datos, bloque });
                    }
                });
            });
            this.guardando = true;
            try {
                const data = await window.request(config.urls.guardar, { method: 'PUT', body: { filas, observaciones: this.observaciones } });
                this.sucio = false;
                sessionStorage.setItem('flash', JSON.stringify({ type: 'success', message: data.message }));
                window.location.reload();
            } catch (e) {
                window.handleRequestError(e);
            } finally {
                this.guardando = false;
            }
        },

        n(v) {
            return Number(v || 0).toLocaleString('es-PE');
        },
    };
}
