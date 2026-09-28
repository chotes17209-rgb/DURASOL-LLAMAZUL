import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import Swal from 'sweetalert2';
import TomSelect from 'tom-select';
import Chart from 'chart.js/auto';
import liquidacionEditor from './liquidacion-editor';
import parteEditor from './parte-editor';

window.Alpine = Alpine;
window.Swal = Swal;
window.Chart = Chart;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

/* ------------------------------------------------------------------ *
 * Avisos (todos con SweetAlert2)
 * ------------------------------------------------------------------ */
export const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3200,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

const alertColors = {
    confirmButtonColor: '#1a3a80',
    cancelButtonColor: '#64748b',
};

export function notify(type, message) {
    toast.fire({ icon: type, title: message });
}

export function alertError(title, messages) {
    const list = Array.isArray(messages) ? messages : [messages];
    return Swal.fire({
        icon: 'error',
        title,
        html: list.length > 1
            ? `<ul class="text-left text-sm space-y-1">${list.map((m) => `<li>• ${escapeHtml(m)}</li>`).join('')}</ul>`
            : escapeHtml(list[0] ?? ''),
        ...alertColors,
    });
}

export async function confirmAction({ title, text, confirmText = 'Sí, continuar', icon = 'question', danger = false, input = null }) {
    const result = await Swal.fire({
        icon,
        title,
        text,
        input: input ? 'text' : undefined,
        inputPlaceholder: input ?? undefined,
        inputValidator: input ? (v) => (!v ? 'Este dato es obligatorio' : undefined) : undefined,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: danger,
        ...alertColors,
        confirmButtonColor: danger ? '#b91c1c' : alertColors.confirmButtonColor,
    });
    return result.isConfirmed ? (input ? result.value : true) : false;
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

window.notify = notify;
window.confirmAction = confirmAction;
window.alertError = alertError;

/* ------------------------------------------------------------------ *
 * HTTP con manejo uniforme de errores
 * ------------------------------------------------------------------ */
export async function request(url, { method = 'GET', body = null, json = false } = {}) {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: json || method !== 'GET' ? 'application/json' : 'text/html',
        'X-CSRF-TOKEN': csrf(),
    };
    let payload = body;
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }
    // Laravel necesita _method para PUT/PATCH/DELETE enviados como FormData
    let realMethod = method;
    if (payload instanceof FormData && !['GET', 'POST'].includes(method)) {
        payload.append('_method', method);
        realMethod = 'POST';
    }

    const response = await fetch(url, { method: realMethod, headers, body: payload, credentials: 'same-origin' });

    if (response.status === 419) {
        await alertError('Sesión expirada', 'La sesión ha expirado. La página se recargará.');
        window.location.reload();
        throw new Error('csrf');
    }
    if (response.status === 401) {
        window.location.href = '/login';
        throw new Error('auth');
    }

    const type = response.headers.get('content-type') || '';
    const data = type.includes('application/json') ? await response.json() : await response.text();

    if (!response.ok) {
        const error = new Error('request');
        error.status = response.status;
        error.data = data;
        throw error;
    }
    return data;
}
window.request = request;

export function handleRequestError(error, form = null) {
    if (error.message === 'csrf' || error.message === 'auth') return;
    if (error.status === 422 && error.data?.errors) {
        if (form) showFormErrors(form, error.data.errors);
        alertError('Datos incompletos o no válidos', Object.values(error.data.errors).flat());
        return;
    }
    if (error.status === 422) {
        alertError('No se pudo completar', error.data?.message || 'Verifique los datos ingresados.');
        return;
    }
    if (error.status === 403) {
        alertError('Sin permiso', error.data?.message || 'El usuario no tiene acceso a esta acción.');
        return;
    }
    if (error.status === 404) {
        alertError('No encontrado', 'El registro ya no existe o fue eliminado.');
        return;
    }
    alertError('Ocurrió un error', error.data?.message || 'No se pudo completar la operación. Intente nuevamente.');
    console.error(error);
}
window.handleRequestError = handleRequestError;

/* ------------------------------------------------------------------ *
 * Errores de validación junto a cada campo
 * ------------------------------------------------------------------ */
function clearFormErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
        el.textContent = '';
        el.classList.add('hidden');
    });
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
}

function showFormErrors(form, errors) {
    clearFormErrors(form);
    Object.entries(errors).forEach(([field, messages]) => {
        const htmlName = field.includes('.') ? field.replace(/\.(\w+)/g, '[$1]') : field;
        const holder = form.querySelector(`[data-error-for="${field}"]`) || form.querySelector(`[data-error-for="${htmlName}"]`);
        if (holder) {
            holder.textContent = messages[0];
            holder.classList.remove('hidden');
        }
        const input = form.querySelector(`[name="${htmlName}"], [name="${htmlName}[]"]`);
        if (input) {
            input.classList.add('is-invalid');
            input.tomselect?.wrapper.classList.add('is-invalid');
        }
    });
}

/* ------------------------------------------------------------------ *
 * Inicialización de componentes dentro de un contenedor
 * ------------------------------------------------------------------ */
export function initComponents(root = document) {
    root.querySelectorAll('select[data-tom]:not(.tomselected)').forEach((el) => {
        const remote = el.dataset.remote;
        const options = {
            allowEmptyOption: true,
            maxOptions: 300,
            placeholder: el.getAttribute('placeholder') || 'Seleccionar...',
            plugins: el.multiple ? ['remove_button'] : [],
            render: { no_results: () => '<div class="no-results p-2 text-slate-400">Sin resultados</div>' },
            dropdownParent: 'body',
        };
        if (remote) {
            Object.assign(options, {
                valueField: 'id',
                labelField: 'text',
                searchField: ['text'],
                load: (query, callback) => {
                    request(`${remote}${remote.includes('?') ? '&' : '?'}q=${encodeURIComponent(query)}`, { json: true })
                        .then((data) => callback(data.results ?? data))
                        .catch(() => callback());
                },
            });
        }
        const ts = new TomSelect(el, options);
        ts.on('change', () => el.dispatchEvent(new Event('ts-change', { bubbles: true })));
    });

    root.querySelectorAll('canvas[data-chart]:not([data-chart-ready])').forEach((canvas) => {
        canvas.dataset.chartReady = '1';
        new Chart(canvas, JSON.parse(canvas.dataset.chart));
    });
}
window.initComponents = initComponents;

/* ------------------------------------------------------------------ *
 * Modal global cargado por AJAX (admite modales apilados: ver → editar)
 * ------------------------------------------------------------------ */
document.addEventListener('alpine:init', () => {
    Alpine.store('modal', {
        stack: [],
        async open(url, size = 'lg') {
            const entry = { id: `m${Date.now()}${Math.floor(Math.random() * 1000)}`, url, size, html: '', loading: true };
            this.stack.push(entry);
            try {
                const html = await request(url);
                const item = this.stack.find((m) => m.id === entry.id);
                if (!item) return;
                item.html = html;
                item.loading = false;
                await Alpine.nextTick();
                const el = document.querySelector(`[data-modal-id="${entry.id}"] [data-modal-content]`);
                if (el) {
                    initComponents(el);
                    el.querySelector('input:not([type=hidden]):not([readonly]):not([disabled]), textarea')?.focus();
                }
            } catch (error) {
                this.stack = this.stack.filter((m) => m.id !== entry.id);
                handleRequestError(error);
            }
        },
        close() {
            const top = this.stack[this.stack.length - 1];
            if (top) {
                document.querySelectorAll(`[data-modal-id="${top.id}"] select.tomselected`).forEach((s) => s.tomselect?.destroy());
            }
            this.stack.pop();
        },
        async reload() {
            const top = this.stack[this.stack.length - 1];
            if (!top) return;
            this.close();
            await this.open(top.url, top.size);
        },
    });

    Alpine.data('liquidacionEditor', liquidacionEditor);
    Alpine.data('parteEditor', parteEditor);
});

window.openModal = (url, size) => Alpine.store('modal').open(url, size);

/* ------------------------------------------------------------------ *
 * Tablas remotas: filtros, búsqueda y paginación sin recargar la página
 * ------------------------------------------------------------------ */
export async function refreshTables(scope = document) {
    const tables = scope.querySelectorAll('[data-remote-table]');
    await Promise.all([...tables].map((t) => loadTable(t, t.dataset.currentUrl || null)));
}
window.refreshTables = refreshTables;

async function loadTable(container, url = null) {
    const filters = container.querySelector('[data-table-filters]');
    const target = container.querySelector('[data-table-body]');
    let finalUrl = url;
    if (!finalUrl) {
        const params = filters ? new URLSearchParams(new FormData(filters)) : new URLSearchParams();
        [...params.keys()].forEach((k) => {
            if (params.get(k) === '') params.delete(k);
        });
        finalUrl = `${container.dataset.url}${params.toString() ? `?${params.toString()}` : ''}`;
    }
    target.classList.add('opacity-50', 'pointer-events-none');
    try {
        target.innerHTML = await request(finalUrl);
        container.dataset.currentUrl = finalUrl;
        initComponents(target);
        if (container.hasAttribute('data-sync-url')) {
            const qs = finalUrl.split('?')[1];
            window.history.replaceState({}, '', `${window.location.pathname}${qs ? `?${qs}` : ''}`);
        }
    } catch (error) {
        handleRequestError(error);
    } finally {
        target.classList.remove('opacity-50', 'pointer-events-none');
    }
}

let filterTimer;
document.addEventListener('input', (e) => {
    const filters = e.target.closest('[data-table-filters]');
    if (!filters || !e.target.matches('input[type=text], input[type=search]')) return;
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => loadTable(filters.closest('[data-remote-table]')), 350);
});
document.addEventListener('change', (e) => {
    const filters = e.target.closest('[data-table-filters]');
    if (!filters || e.target.matches('input[type=text], input[type=search]')) return;
    loadTable(filters.closest('[data-remote-table]'));
});
document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-limpiar-filtros]');
    if (!boton) return;
    const filters = boton.closest('[data-table-filters]');
    filters.querySelectorAll('input, select').forEach((el) => {
        if (el.type === 'hidden' || el.type === 'checkbox') return;
        if (el.tomselect) el.tomselect.clear(true);
        else el.value = '';
    });
    loadTable(filters.closest('[data-remote-table]'));
});
document.addEventListener('submit', (e) => {
    const filters = e.target.closest('[data-table-filters]');
    if (!filters) return;
    e.preventDefault();
    loadTable(filters.closest('[data-remote-table]'));
});

/* ------------------------------------------------------------------ *
 * Delegación de eventos: abrir modal, eliminar, acciones con confirmación
 * ------------------------------------------------------------------ */
document.addEventListener('click', async (e) => {
    const pageLink = e.target.closest('[data-remote-table] [data-table-body] nav a[href]');
    if (pageLink) {
        e.preventDefault();
        loadTable(pageLink.closest('[data-remote-table]'), pageLink.href);
        return;
    }

    const modalTrigger = e.target.closest('[data-modal-url]');
    if (modalTrigger) {
        e.preventDefault();
        let url = modalTrigger.dataset.modalUrl;
        // data-con-fecha: el formulario se abre con la fecha elegida en el filtro «Desde» de la página.
        if (modalTrigger.hasAttribute('data-con-fecha')) {
            const campo = document.querySelector('[data-table-filters] [name="desde"]');
            const fecha = campo?.value || new URLSearchParams(window.location.search).get('desde');
            if (fecha) url += `${url.includes('?') ? '&' : '?'}fecha=${encodeURIComponent(fecha)}`;
        }
        Alpine.store('modal').open(url, modalTrigger.dataset.modalSize || 'lg');
        return;
    }

    if (e.target.closest('[data-modal-close]')) {
        e.preventDefault();
        // Ficha abierta como página (sin ventana): «Cerrar» vuelve a la pantalla anterior.
        if (!Alpine.store('modal').stack.length && e.target.closest('[data-vista-parcial]')) {
            volverAtras();
            return;
        }
        Alpine.store('modal').close();
        return;
    }

    const deleteTrigger = e.target.closest('[data-delete-url]');
    if (deleteTrigger) {
        e.preventDefault();
        const ok = await confirmAction({
            title: deleteTrigger.dataset.title || '¿Eliminar este registro?',
            text: deleteTrigger.dataset.text || 'La eliminación quedará registrada en el historial.',
            confirmText: 'Sí, eliminar',
            icon: 'warning',
            danger: true,
        });
        if (!ok) return;
        try {
            afterSuccess(await request(deleteTrigger.dataset.deleteUrl, { method: 'DELETE', body: new FormData() }), deleteTrigger);
        } catch (error) {
            handleRequestError(error);
        }
        return;
    }

    // Descargas PDF / Excel: agrega los filtros de la página y el formato.
    const exportTrigger = e.target.closest('[data-export]');
    if (exportTrigger) {
        const url = new URL(exportTrigger.getAttribute('href'), window.location.origin);
        const filtros = document.querySelector('[data-table-filters]');
        if (filtros) {
            new FormData(filtros).forEach((valor, clave) => { if (valor !== '') url.searchParams.set(clave, valor); });
        }
        url.searchParams.set('formato', exportTrigger.dataset.export);
        exportTrigger.href = url.toString();
        return;
    }

    const actionTrigger = e.target.closest('[data-action-url]');
    if (actionTrigger) {
        e.preventDefault();
        const ds = actionTrigger.dataset;
        let answer = true;
        if (ds.confirm) {
            answer = await confirmAction({
                title: ds.confirm,
                text: ds.text,
                confirmText: ds.confirmText || 'Sí, continuar',
                icon: ds.icon || 'question',
                danger: ds.danger === '1',
                input: ds.input || null,
            });
            if (!answer) return;
        }
        const body = new FormData();
        if (ds.input && typeof answer === 'string') body.append(ds.inputName || 'motivo', answer);
        try {
            afterSuccess(await request(ds.actionUrl, { method: ds.method || 'POST', body }), actionTrigger);
        } catch (error) {
            handleRequestError(error);
        }
    }
});

document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-ajax]');
    if (!form) return;
    e.preventDefault();

    if (form.dataset.confirm) {
        const ok = await confirmAction({ title: form.dataset.confirm, text: form.dataset.confirmText });
        if (!ok) return;
    }

    const submit = form.querySelector('[type=submit]');
    const original = submit?.innerHTML;
    if (submit) {
        submit.disabled = true;
        submit.innerHTML = '<span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span> Guardando...';
    }
    clearFormErrors(form);
    try {
        const body = new FormData(form);
        const method = (body.get('_method') || form.getAttribute('method') || 'POST').toUpperCase();
        body.delete('_method');
        afterSuccess(await request(form.action, { method, body }), form);
    } catch (error) {
        handleRequestError(error, form);
    } finally {
        if (submit) {
            submit.disabled = false;
            submit.innerHTML = original;
        }
    }
});

function volverAtras() {
    if (window.history.length > 1) window.history.back();
    else window.location.href = '/';
}

export function afterSuccess(data, origin = null) {
    if (data?.redirect) {
        sessionStorage.setItem('flash', JSON.stringify({ type: data.type || 'success', message: data.message }));
        window.location.href = data.redirect;
        return;
    }
    // Un formulario guardado cierra su modal; eliminar o accionar desde un modal solo lo recarga.
    const isForm = origin?.tagName === 'FORM';
    const inModal = origin?.closest?.('[data-modal-content]');
    if (isForm && origin.closest('[data-vista-parcial]') && !Alpine.store('modal').stack.length && data?.keepModal !== true) {
        if (data?.message) sessionStorage.setItem('flash', JSON.stringify({ type: data.type || 'success', message: data.message }));
        volverAtras();
        return;
    }
    if (inModal && isForm && data?.keepModal !== true) Alpine.store('modal').close();
    if (inModal && !isForm && data?.closeModal) Alpine.store('modal').close();
    if (data?.message) notify(data.type || 'success', data.message);
    if (data?.reloadModal) Alpine.store('modal').reload();
    if (data?.reloadPage) {
        if (data.message) sessionStorage.setItem('flash', JSON.stringify({ type: data.type || 'success', message: data.message }));
        window.location.reload();
        return;
    }
    refreshTables();
    document.dispatchEvent(new CustomEvent('erp:saved', { detail: data }));
}
window.afterSuccess = afterSuccess;

/* ------------------------------------------------------------------ *
 * Arranque
 * ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', () => {
    initComponents();
    const flash = sessionStorage.getItem('flash');
    if (flash) {
        sessionStorage.removeItem('flash');
        const { type, message } = JSON.parse(flash);
        if (message) notify(type, message);
    }
    document.querySelectorAll('[data-flash]').forEach((el) => notify(el.dataset.type || 'success', el.dataset.flash));
    const abrirModal = sessionStorage.getItem('abrirModal');
    if (abrirModal) {
        sessionStorage.removeItem('abrirModal');
        Alpine.store('modal').open(abrirModal, 'lg');
    }
});

Alpine.plugin(collapse);
Alpine.start();

/* ------------------------------------------------------------------ *
 * Hojas de captura (parte diario, liquidación): moverse entre celdas
 * con las flechas del teclado, como en Excel.
 * ------------------------------------------------------------------ */
const FLECHAS = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

function celdasDeFila(fila) {
    return [...fila.querySelectorAll('.cell-input')].filter((c) => !c.disabled && c.offsetParent !== null);
}

function enfocarCelda(celda) {
    if (!celda) return;
    celda.focus();
    if (celda.tagName === 'INPUT') celda.select();
}

document.addEventListener('keydown', (e) => {
    if (!FLECHAS.includes(e.key) || e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
    const celda = e.target;
    if (!celda.matches?.('.cell-input')) return;
    const td = celda.closest('td');
    const fila = td?.closest('tr');
    const cuerpo = fila?.closest('tbody');
    if (!cuerpo) return;

    // En texto, izquierda/derecha mueven el cursor hasta llegar al borde del contenido.
    if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && celda.tagName === 'INPUT' && typeof celda.selectionStart === 'number') {
        const { selectionStart: ini, selectionEnd: fin, value } = celda;
        if (e.key === 'ArrowLeft' && !(ini === 0 && fin === 0) && !(ini === 0 && fin === value.length)) return;
        if (e.key === 'ArrowRight' && !(ini === value.length) && !(ini === 0 && fin === value.length)) return;
    }

    e.preventDefault();
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        const celdas = celdasDeFila(fila);
        enfocarCelda(celdas[celdas.indexOf(celda) + (e.key === 'ArrowRight' ? 1 : -1)]);
        return;
    }

    // Arriba/abajo: misma columna en la fila anterior o siguiente que tenga una celda editable.
    const filas = [...cuerpo.rows];
    const paso = e.key === 'ArrowDown' ? 1 : -1;
    for (let i = filas.indexOf(fila) + paso; i >= 0 && i < filas.length; i += paso) {
        const destino = filas[i].cells[td.cellIndex]?.querySelector('.cell-input');
        if (destino && !destino.disabled && destino.offsetParent !== null) {
            enfocarCelda(destino);
            return;
        }
    }
});
