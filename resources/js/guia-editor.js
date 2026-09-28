/**
 * Formulario de guía de planta: filtra instalaciones por empresa, propone precio,
 * chofer y camión de la instalación, y muestra el control de masa en vivo.
 */
export default function guiaEditor(config) {
    return {
        empresaId: config.empresaId ? String(config.empresaId) : '',
        instalacionId: config.instalacionId ? String(config.instalacionId) : '',
        instalaciones: config.instalaciones,
        precios: config.precios,
        filas: config.filas,
        aceptoDiferencia: false,

        get instalacionesEmpresa() {
            return this.instalaciones.filter((i) => String(i.empresa_id) === this.empresaId);
        },

        cambiarEmpresa() {
            if (!this.instalacionesEmpresa.some((i) => String(i.id) === this.instalacionId)) {
                this.instalacionId = this.instalacionesEmpresa.length === 1 ? String(this.instalacionesEmpresa[0].id) : '';
                this.cambiarInstalacion();
            }
        },

        cambiarInstalacion() {
            const inst = this.instalaciones.find((i) => String(i.id) === this.instalacionId);
            if (!inst) return;
            this.setSelect('chofer_id', inst.chofer_id);
            this.setSelect('vehiculo_id', inst.vehiculo_id);
            this.filas.forEach((f) => {
                const precio = this.precios?.[inst.id]?.[f.producto_id];
                if (precio !== undefined) f.precio_compra = precio;
            });
        },

        setSelect(name, value) {
            if (!value) return;
            const el = this.$root.querySelector(`select[name="${name}"]`);
            if (!el) return;
            if (el.tomselect) el.tomselect.setValue(String(value));
            else el.value = String(value);
        },

        enviados(f) {
            return (+f.vacios_enviados || 0) + (+f.colores_enviados || 0) + (+f.cambios_enviados || 0);
        },
        diferencia(f) {
            return this.enviados(f) - (+f.cantidad_guia || 0);
        },
        get totalGuia() {
            return this.filas.reduce((s, f) => s + (+f.cantidad_guia || 0), 0);
        },
        get totalEnviado() {
            return this.filas.reduce((s, f) => s + this.enviados(f), 0);
        },
        get importe() {
            return this.filas.reduce((s, f) => s + (+f.cantidad_guia || 0) * (+f.precio_compra || 0), 0);
        },
        get hayDiferencia() {
            return this.filas.some((f) => this.diferencia(f) !== 0);
        },
        /** Atajo: todo lo de la guía sale como vacío plomo. */
        igualarVacios(f) {
            f.vacios_enviados = Math.max(0, (+f.cantidad_guia || 0) - (+f.colores_enviados || 0) - (+f.cambios_enviados || 0));
        },
        money(v) {
            return 'S/ ' + Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
}
