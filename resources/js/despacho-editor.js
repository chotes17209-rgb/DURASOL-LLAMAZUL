/**
 * Totales en vivo del despacho a choferes (salida y retorno).
 */
export default function despachoEditor(config = {}) {
    return {
        filas: config.filas || [],
        vehiculos: config.vehiculos || {},
        tipos: config.tipos || {},

        cambiarChofer(event) {
            const id = event.target.value;
            const vehiculo = this.vehiculos[id];
            const tipo = this.tipos[id];
            const vSelect = this.$root.querySelector('select[name="vehiculo_id"]');
            if (vehiculo && vSelect) vSelect.tomselect ? vSelect.tomselect.setValue(String(vehiculo)) : (vSelect.value = String(vehiculo));
            const tSelect = this.$root.querySelector('select[name="tipo"]');
            if (tipo && tSelect) tSelect.value = tipo;
        },
        vendidos(f) {
            return Math.max(0, (+f.llenos_salida || 0) - (+f.llenos_retorno || 0) - (+f.cambios_retorno || 0));
        },
        total(campo) {
            return this.filas.reduce((s, f) => s + (+f[campo] || 0), 0);
        },
        get totalVendidos() {
            return this.filas.reduce((s, f) => s + this.vendidos(f), 0);
        },
    };
}
