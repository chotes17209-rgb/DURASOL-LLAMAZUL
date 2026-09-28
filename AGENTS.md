# Guía para asistentes de código

ERP de distribución de gas (Durasol · Llamazul) en Laravel 13. Lee `README.md` para el dominio y los módulos.

- Idioma: todo lo visible al usuario y los nombres de dominio (modelos, tablas, rutas) están en **español**.
- Reglas de negocio en `app/Services`; los controladores solo validan (Form Requests) y orquestan.
- Todo cambio de stock pasa por `StockService::sincronizar()` desde `LogisticaService`; nunca escribir `stock_movimientos` a mano.
- Los precios nunca se sobrescriben: cada cambio es una fila nueva con `vigente_desde`.
- Toda tabla de negocio usa el trait `Auditable` (historial). Eventos especiales con `AuditLogger::event()`.
- CRUD en modales: los controladores devuelven vistas parciales para `data-modal-url` y JSON `{message}` (ver `Controller::ok()`); los formularios usan `<x-form-modal>` y `data-ajax`.
- Consultas compatibles con MySQL y PostgreSQL (evitar funciones propias de un motor).
- Antes de subir cambios: `vendor/bin/pint` y `php artisan test`.
