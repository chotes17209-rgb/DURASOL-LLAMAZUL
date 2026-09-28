# Durasol · Llamazul — ERP de distribución de gas

Sistema web para la distribuidora de gas **Mr. Durasol Perú S.A.C.** y **Llamazul**: movimiento de masa
(balones llenos, vacíos, de color y cambios), guías de compra en la planta Solgas, despachos a choferes,
liquidaciones diarias, créditos y cobranzas, caja, precios de compra y venta, flota vehicular e historial completo.

Hecho en **Laravel 13 + MySQL** (también funciona con PostgreSQL), con Tailwind CSS, Alpine.js,
SweetAlert2 (todos los avisos), Tom Select (buscadores) y Chart.js (gráficos). Todo el CRUD se hace en
**ventanas modales**, incluida la opción **Ver**, y cada registro tiene su **historial de cambios**.

---

## Módulos

| Área | Qué hace |
|---|---|
| **Panel de control** | Venta del día y del mes, gráficos, créditos por cobrar, saldo de caja, stock y alertas de documentos vehiculares. |
| **Logística · Stock y kardex** | Stock de llenos por empresa, cambios, vacíos plomo y de color; kardex con saldo por producto/estado/empresa. |
| **Logística · Guías de planta** | Salida a planta con N° de guía, empresa e instalación (código de 8 dígitos). Lo que dice la guía debe salir en vacíos/colores/cambios; al volver se registran los llenos y los vacíos rechazados. **Control de masa**: lo que sale debe volver. |
| **Logística · Despachos** | Salida de balones a cada chofer (varias vueltas por día) y su retorno: llenos no vendidos, vacíos, colores y cambios. Calcula los vendidos y los compara con la liquidación. |
| **Logística · Canjes y movimientos** | Canje de colores por plomos; stock inicial, ingreso de vacíos, préstamos, mermas y ajustes. |
| **Liquidaciones** | Fecha → chofer → clientes (cantidad, vacíos, método de pago, crédito) → FISE (S/ 20, 30, 43) por cliente → cobranzas y gastos. **Efectivo = venta + cobranzas − créditos − vouchers − FISE − gastos.** Caja la cierra con el efectivo contado. |
| **Clientes / Créditos** | Cartera por chofer, precios por cliente, cuenta corriente; las cobranzas pagan primero la deuda más antigua. |
| **Precios** | Compra por **empresa e instalación**; venta por **cliente**, con historial y ajuste masivo (“subir/bajar S/ 0.70 a todos”). |
| **Caja** | Ingresos de liquidaciones y cobranzas, depósitos bancarios, gastos, caja chica; saldo diario. |
| **Reportes** | Hoja de liquidación diaria (como “RESUMEN GNRAL”), detalle de ventas exportable a Excel (CSV), caja por día (como “CAJA GNRAL”). |
| **Flota y personal** | Vehículos con SOAT, revisión técnica, DGH/OSINERGMIN, póliza, etc. (semáforo de vencimientos y archivos), mantenimientos, choferes e instalaciones. |
| **Administración** | Empresas, productos, cuentas bancarias, usuarios e **historial de todo** (quién, cuándo, antes/después). |

### Roles

| Usuario | Ve |
|---|---|
| `admin` (gerencia) | Todo. Es el único que puede reabrir liquidaciones y cambiar precios de compra. |
| `logistica` | Stock, guías, despachos, canjes, flota, choferes, instalaciones y precios de compra (solo lectura). |
| `caja` | Caja, depósitos, cuentas bancarias, liquidaciones (para cerrarlas), clientes y créditos. |
| `liquidaciones` | Liquidaciones, clientes, créditos, precios de venta y reportes de ventas. |

Contraseña de los usuarios de demostración: **`demo1234`** (cámbiala en *Administración → Usuarios*).

---

## Datos importados del Excel `RENTABILIDAD SETIEMBRE`

El seeder `database/seeders/ExcelSeeder.php` carga los datos (convertidos a JSON en `database/seeders/data`):

- **DATA** → 357 clientes con su chofer responsable y precios (S10, M10, S45, C10, C45, K10, K45); precios de compra de Durasol y Llamazul; placas.
- **VENTAS** → 11 200 filas agrupadas en 1 552 liquidaciones históricas (10 008 ventas, S/ 18 170 711.80), créditos y cobranzas. Las cobranzas se aplican a los créditos más antiguos: quedan **S/ 1 620 820.73** por cobrar.
- **FISES** → S/ 487 177 en vales, asignados a la liquidación del chofer de ese día.
- **STOCK** → compras de agosto como guías históricas (no alteran el stock actual).
- **B.LLENO / B.VACIO** → foto del almacén al 25/09: el stock final coincide con el Excel (S10 llenos 2 187, S45 75, M10 26, cambios 34/3, C10 308, C45 116, vacíos plomo 815/19, colores 336/5).
- **RESUMEN GNRAL / CAJA GNRAL** → depósitos del 25/09 y totales de agosto.

**Pendiente de completar por la empresa:** el Excel no trae los códigos de 8 dígitos de las instalaciones (se crearon
`10000001` Durasol y `20000001` Llamazul), ni marca/modelo/SOAT de los vehículos, ni los números reales de guía del 25/09.

---

## Instalación local (Windows con XAMPP o Laragon)

Requisitos: PHP 8.3+, Composer, Node 20+, MySQL 8 / MariaDB 10.6+.

```bash
git clone https://github.com/chotes17209-rgb/durasol-llamazul.git
cd durasol-llamazul
composer install
npm install && npm run build
cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
```

Crea la base de datos `durasol` en MySQL (phpMyAdmin) y revisa `DB_*` en `.env`. Luego:

```bash
php artisan migrate --seed    # crea tablas, usuarios y carga el Excel (~30 s)
php artisan serve             # http://localhost:8000
```

Para trabajar en los estilos: `npm run dev` en otra terminal.

## Pruebas

```bash
php artisan test              # usa la base durasol_test (MySQL); ver phpunit.xml
vendor/bin/pint               # formato de código
```

## Despliegue en Render

El repositorio incluye `Dockerfile` y `render.yaml` (Blueprint):

1. En Render: **New → Blueprint** y elige este repositorio.
2. Render crea la app web y una base de datos PostgreSQL, y genera la clave de la aplicación.
3. En el primer arranque se ejecutan las migraciones y se cargan los datos del Excel.

> Render no ofrece MySQL administrado; por eso el Blueprint usa PostgreSQL (el sistema funciona igual con ambos y las pruebas pasan en los dos).
> La base de datos gratuita de Render **vence a los 30 días** y los archivos subidos (PDF de documentos vehiculares) se pierden en cada despliegue del plan gratuito:
> para producción usa un plan pagado con disco o un almacenamiento externo (S3).

---

## Estructura del código

- `app/Services` — reglas de negocio: `StockService` (kardex), `LogisticaService` (qué mueve cada documento),
  `LiquidacionService` (fórmula y cierre), `CuentaService` (créditos FIFO), `CajaService`, `PrecioService` (precios vigentes).
- `app/Http/Controllers` — por área (Admin, Flota, Logistica, Precios, Ventas, Caja); validación en `app/Http/Requests`.
- `app/Models/Concerns/Auditable.php` — historial automático de cada modelo.
- `resources/views` — Blade; `components/` (modales, campos, tablas); `resources/js` — Alpine (`liquidacion-editor.js`, …).
