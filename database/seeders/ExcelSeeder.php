<?php

namespace Database\Seeders;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoLiquidacion;
use App\Enums\MetodoPago;
use App\Enums\TipoChofer;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\CuentaBancaria;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\Liquidacion;
use App\Models\Parte;
use App\Models\ParteFila;
use App\Models\PrecioCompra;
use App\Models\Producto;
use App\Models\Vehiculo;
use App\Services\AlmacenService;
use App\Services\CostoService;
use App\Services\LiquidacionService;
use App\Support\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa el Excel "RENTABILIDAD SETIEMBRE" (convertido a JSON en database/seeders/data):
 *  - DATA: clientes, chofer responsable, precios de venta y de compra, placas.
 *  - VENTAS: 11 200 filas agrupadas en liquidaciones por fecha y chofer (ventas, créditos y cobranzas).
 *  - FISES: vales FISE por chofer y día.
 *  - Cuadro de instalaciones Solgas con sus precios de compra (aún no validados en factura).
 *  - Parte de logística del 14/09 (hojas LLENOS y VACÍOS) y B.LLENO / B.VACIO del 25/09.
 *  - RESUMEN GNRAL / CAJA GNRAL: depósitos.
 */
class ExcelSeeder extends Seeder
{
    private const FECHA_PRECIOS = '2026-01-01';

    /** Tipo de cada chofer según cómo trabaja en el Excel. */
    private const TIPOS = [
        'URBANO' => TipoChofer::Local, 'MISAEL' => TipoChofer::Local, 'RONALD' => TipoChofer::Local, 'JORGE' => TipoChofer::Local,
        'ZADITH' => TipoChofer::Almacen,
        'ABEL' => TipoChofer::Ruta, 'RUFINO' => TipoChofer::Ruta, 'FREDY' => TipoChofer::Ruta, 'AGUILAR' => TipoChofer::Ruta,
        'MILTON' => TipoChofer::Ruta, 'RICHAR' => TipoChofer::Ruta, 'CARRETA' => TipoChofer::Ruta, 'BRAYAN' => TipoChofer::Ruta, 'CELESTINO' => TipoChofer::Ruta,
        'ABUELO' => TipoChofer::Planta,
    ];

    private array $choferes = [];

    private array $vehiculos = [];

    private array $clientesPorCodigo = [];

    private array $clientesPorNombre = [];

    private array $productos = [];

    private array $empresas = [];

    private CostoService $costos;

    public function run(): void
    {
        AuditLogger::withoutAuditing(function () {
            DB::disableQueryLog();
            $data = $this->leer('data.json');

            $this->productos = Producto::pluck('id', 'codigo')->all();
            $this->costos = app(CostoService::class);
            $this->empresas = Empresa::pluck('id', 'nombre')->all();

            $this->vehiculosYChoferes($data['placas']);
            $instalaciones = $this->instalacionesYPreciosCompra($data['precios_compra']);
            $this->clientes($data['clientes']);
            $this->command?->info('Clientes y precios importados: '.count($this->clientesPorCodigo));

            $this->ventas();
            $this->command?->info('Ventas importadas: '.Liquidacion::count().' liquidaciones');

            $this->fises();
            $this->parte14Setiembre($instalaciones);
            $this->parte25Setiembre($instalaciones);
            $this->depositos();
            $this->comprasHistoricas();
            $this->command?->info('Partes de almacén y depósitos importados.');
        });
    }

    /* ------------------------------------------------------------------ */

    private function vehiculosYChoferes(array $placas): void
    {
        $esPlaca = fn (?string $p) => $p && preg_match('/^[A-Z0-9]{3}-\d{3}$/', $p);

        // Placas conocidas por otras hojas (B.LLENO) además de DATA.
        $placas = array_merge($placas, [['ABUELO', 'W2S-907'], ['RONALD', 'W3L-816'], ['RUFINO', 'CET-858'], ['MILTON', 'AXI-745'], ['ZADITH', 'CEO-734']]);

        foreach ($placas as [$responsable, $placa]) {
            if (! $esPlaca($placa)) {
                continue;
            }
            $vehiculo = Vehiculo::firstOrCreate(['placa' => $placa], ['tipo' => 'camion', 'estado' => 'operativo', 'observaciones' => 'Importado del Excel. Completar marca, modelo y documentos.']);
            $this->vehiculos[$placa] = $vehiculo->id;
            $chofer = $this->chofer($responsable);
            if ($chofer && ! $chofer->vehiculo_id) {
                $chofer->update(['vehiculo_id' => $vehiculo->id]);
            }
        }
    }

    private function chofer(?string $alias): ?Chofer
    {
        $alias = $this->normalizarChofer($alias);
        if (! $alias || ! (isset(self::TIPOS[$alias]) || in_array($alias, ['MELISSA', 'ALEX', 'RULDER', 'OTROS'], true))) {
            return null;
        }
        if (! isset($this->choferes[$alias])) {
            $this->choferes[$alias] = Chofer::firstOrCreate(['alias' => $alias], [
                'tipo' => self::TIPOS[$alias] ?? TipoChofer::Otro,
                'activo' => true,
                'observaciones' => 'Importado del Excel.',
            ]);
        }

        return $this->choferes[$alias];
    }

    private function normalizarChofer(?string $alias): ?string
    {
        $alias = mb_strtoupper(trim((string) $alias));

        return match ($alias) {
            'RONAL' => 'RONALD',
            'JORGUE' => 'JORGE',
            'MIZAEL' => 'MISAEL',
            'ABULO' => 'ABUELO',
            'ZADIT' => 'ZADITH',
            '' => 'OTROS',
            default => $alias,
        };
    }

    /**
     * Cuadro de instalaciones de logística: código Solgas de 8 dígitos, responsable, placas, planta
     * y precio de compra. "No validado" = la última variación de precio aún no se ve en las facturas.
     *
     * @return array<string, Instalacion> código => instalación
     */
    private function instalacionesYPreciosCompra(array $preciosExcel): array
    {
        $cuadro = [
            // empresa, responsable, código, placas, planta, S10, S45, M10
            ['DURASOL', 'AGUILAR', '62170831', 'W6D-892', 'P.HUA', 41.30, 194.50, 37.80],
            ['DURASOL', 'ABUELO', '62170833', 'W2S-907', 'P.HUA', 41.30, 194.50, 37.80],
            ['DURASOL', 'FREDY', '62173099', 'BRU-782', 'P.HUA', 41.30, 194.50, 37.80],
            ['DURASOL', 'MILTON', '62174640', 'VIQUES', 'P.HUA', 41.30, 194.50, 37.80],
            ['DURASOL', 'VIQUES', '62174651', 'VIQUES', 'P.HUA', 42.50, null, null],
            ['DURASOL', 'ESPEJO', '62177529', 'W6D-892', 'P.HUA', 36.80, null, null],
            ['DURASOL', 'ESPEJO', '62177530', null, 'P.HUA', 36.80, null, null],
            ['DURASOL', 'ESPEJO', '62177531', null, 'P.HUA', 36.80, null, null],
            ['LLAMAZUL', 'ABEL', '62170835', 'W6F-740', 'P.HUA', 40.50, 207.90, null],
            ['LLAMAZUL', 'RUFINO', '62170838', 'BCL-884', 'P.HUA', 40.50, null, null],
            ['LLAMAZUL', 'HUANCAVELICA', '62174003', 'CET-858, W6N-923', 'P.HUA', 40.50, null, null],
            ['LLAMAZUL', 'MILTON', '62174064', 'MALVINAS', 'P.HUA', 39.50, 207.90, null],
            ['LLAMAZUL', 'LIMA', '62172419', 'MALVINAS', 'P.LIMA', 33.80, 169.20, null],
            ['LLAMAZUL', 'LIMA', '62125291', 'MALVINAS', 'P.LIMA', 33.80, 169.20, null],
            ['LLAMAZUL', 'AYACUCHO', '62172211', 'BCL-884', 'P.AYACUCHO', 36.50, null, null],
        ];

        $instalaciones = [];
        foreach ($cuadro as [$empresa, $responsable, $codigo, $placas, $planta, $s10, $s45, $m10]) {
            $placa = $placas ? trim(explode(',', $placas)[0]) : null;
            if ($placa && preg_match('/^[A-Z0-9]{3}-\d{3}$/', $placa) && ! isset($this->vehiculos[$placa])) {
                $this->vehiculos[$placa] = Vehiculo::firstOrCreate(['placa' => $placa], ['tipo' => 'camion', 'estado' => 'operativo', 'observaciones' => 'Registrado desde el cuadro de instalaciones.'])->id;
            }
            $instalacion = Instalacion::create([
                'codigo' => $codigo,
                'nombre' => "{$responsable} · {$planta}",
                'empresa_id' => $this->empresas[$empresa],
                'planta' => $planta,
                'responsable' => $responsable,
                'placas' => $placas,
                'chofer_id' => $this->chofer($responsable)?->id,
                'vehiculo_id' => $placa ? ($this->vehiculos[$placa] ?? null) : null,
                'activo' => true,
            ]);
            $instalaciones[$codigo] = $instalacion;

            foreach (['S10' => $s10, 'S45' => $s45, 'M10' => $m10] as $producto => $precio) {
                if ($precio === null) {
                    continue;
                }
                // Precio anterior (hoja DATA del Excel de rentabilidad), ya reflejado en facturas.
                if ($anterior = $preciosExcel[$empresa][$producto] ?? null) {
                    PrecioCompra::create([
                        'empresa_id' => $instalacion->empresa_id, 'instalacion_id' => $instalacion->id, 'producto_id' => $this->productos[$producto],
                        'precio' => $anterior, 'vigente_desde' => self::FECHA_PRECIOS, 'motivo' => 'Importado del Excel de rentabilidad (DATA)',
                        'validado' => true, 'validado_at' => self::FECHA_PRECIOS,
                    ]);
                }
                PrecioCompra::create([
                    'empresa_id' => $instalacion->empresa_id, 'instalacion_id' => $instalacion->id, 'producto_id' => $this->productos[$producto],
                    'precio' => $precio, 'vigente_desde' => '2026-09-01', 'motivo' => 'Cuadro de instalaciones de logística',
                    'validado' => false,
                ]);
            }
        }

        return $instalaciones;
    }

    private function clientes(array $clientes): void
    {
        $filasPrecios = [];
        foreach ($clientes as $c) {
            $chofer = $this->chofer($c['responsable']);
            $cliente = Cliente::firstOrCreate(['codigo' => $c['codigo']], [
                'nombre' => mb_strtoupper($c['nombre']),
                'conocido_como' => $c['conocido'] ? mb_strtoupper($c['conocido']) : null,
                'direccion' => $c['direccion'],
                'telefono' => $c['telefono'] ? mb_substr($c['telefono'], 0, 30) : null,
                'correo' => filter_var($c['correo'], FILTER_VALIDATE_EMAIL) ?: null,
                'chofer_id' => $chofer?->id,
                'tipo' => $this->tipoCliente($c['nombre'], $chofer),
                'activo' => true,
            ]);
            $this->registrarCliente($cliente);
            foreach ($c['precios'] as $codigo => $precio) {
                $filasPrecios[] = [
                    'cliente_id' => $cliente->id, 'producto_id' => $this->productos[$codigo], 'precio' => round($precio, 2),
                    'vigente_desde' => self::FECHA_PRECIOS, 'motivo' => 'Importado del Excel (DATA)', 'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($filasPrecios, 500) as $chunk) {
            DB::table('precios_venta')->insert($chunk);
        }
    }

    private function tipoCliente(string $nombre, ?Chofer $chofer): string
    {
        return match (true) {
            str_contains($nombre, 'TRABAJADOR') || str_contains($nombre, 'OFICINA') || str_contains($nombre, 'ESTIBADOR') => 'trabajador',
            (bool) preg_match('/EJERCITO|PNP|INABIF|FAP|HOTEL/', $nombre) => 'institucional',
            $chofer?->tipo === TipoChofer::Ruta => 'ruta',
            default => 'local',
        };
    }

    private function registrarCliente(Cliente $cliente): void
    {
        $this->clientesPorCodigo[$cliente->codigo] = $cliente->id;
        $this->clientesPorNombre[$this->clave($cliente->nombre)] ??= $cliente->id;
    }

    private function clave(?string $texto): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii((string) $texto)));
    }

    /** Busca el cliente por código; si no existe, por nombre; si tampoco, lo crea. */
    private function clienteId(mixed $codigo, ?string $nombre, ?Chofer $chofer): int
    {
        if (is_int($codigo) && isset($this->clientesPorCodigo[$codigo]) && $nombre) {
            return $this->clientesPorCodigo[$codigo];
        }
        $clave = $this->clave($nombre ?: (string) $codigo);
        if (isset($this->clientesPorNombre[$clave])) {
            return $this->clientesPorNombre[$clave];
        }
        $siguiente = max(1000, (int) Cliente::max('codigo') + 1);
        $cliente = Cliente::create([
            'codigo' => $siguiente,
            'nombre' => mb_strtoupper($nombre ?: "CLIENTE {$codigo}"),
            'chofer_id' => $chofer?->id,
            'tipo' => $this->tipoCliente(mb_strtoupper((string) $nombre), $chofer),
            'activo' => true,
            'observaciones' => 'Creado al importar ventas del Excel (no estaba en la hoja DATA).',
        ]);
        $this->registrarCliente($cliente);

        return $cliente->id;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Cada fecha + responsable del Excel = una liquidación cerrada (histórica).
     * Columnas: fecha, fecha_liq, empresa, codigo, placa, responsable, cliente, presentacion, cantidad, precio, total, balones, credito, cobranza
     */
    private function ventas(): void
    {
        $filas = $this->leer('ventas.json');
        // Costo del Excel (columna P) por fecha, empresa y presentación: se toma el más frecuente,
        // así un costo mal digitado en una fila no distorsiona la rentabilidad.
        $costosExcel = [];
        foreach ($filas as $f) {
            if (! empty($f[14])) {
                $k = $f[0].'|'.mb_strtoupper((string) $f[2]).'|'.$f[7];
                $costosExcel[$k][(string) $f[14]] = ($costosExcel[$k][(string) $f[14]] ?? 0) + 1;
            }
        }
        $costosExcel = array_map(function ($c) {
            arsort($c);

            return (float) array_key_first($c);
        }, $costosExcel);
        $grupos = [];
        foreach ($filas as $f) {
            if (! $f[0]) {
                continue;
            }
            $alias = $this->normalizarChofer($f[5]);
            $grupos[$f[0].'|'.$alias][] = $f;
        }
        ksort($grupos);

        /** @var LiquidacionService $service */
        $service = app(LiquidacionService::class);
        $creditos = [];   // cliente_id => [[fecha, item_id, liquidacion_id, monto], ...]
        $pagos = [];      // cliente_id => [[fecha, liquidacion_cobranza_id, monto], ...]

        foreach ($grupos as $clave => $filasGrupo) {
            [$fecha, $alias] = explode('|', $clave);
            $chofer = $this->chofer($alias) ?? $this->chofer('OTROS');
            $fechaLiq = collect($filasGrupo)->pluck(1)->filter()->first() ?? Carbon::parse($fecha)->addDay()->toDateString();
            $placa = collect($filasGrupo)->pluck(4)->first(fn ($p) => isset($this->vehiculos[$p]));

            $liquidacion = Liquidacion::create([
                'codigo' => 'TMP-'.Str::random(10),
                'fecha_venta' => $fecha,
                'fecha_liquidacion' => max($fechaLiq, $fecha),
                'chofer_id' => $chofer->id,
                'vehiculo_id' => $placa ? $this->vehiculos[$placa] : $chofer->vehiculo_id,
                'tipo' => in_array($chofer->tipo, [TipoChofer::Ruta, TipoChofer::Almacen], true) ? $chofer->tipo : TipoChofer::Local,
                'estado' => EstadoLiquidacion::Cerrada,
                'historico' => true,
                'cerrada_at' => Carbon::parse(max($fechaLiq, $fecha))->setTime(18, 0),
                'observaciones' => 'Importada del Excel (hoja VENTAS).',
            ]);
            $liquidacion->updateQuietly(['codigo' => $service->codigoPara($liquidacion->id)]);

            $orden = 0;
            foreach ($filasGrupo as $f) {
                [, , $empresa, $codigo, , , $nombreCliente, $presentacion, $cantidad, $precio, $total, $balones, $credito, $cobranza] = $f;
                $costoExcel = $costosExcel[$f[0].'|'.mb_strtoupper((string) $empresa).'|'.$presentacion] ?? null;
                $clienteId = $this->clienteId($codigo, $nombreCliente, $chofer);
                $empresaId = $this->empresas[mb_strtoupper((string) $empresa)] ?? $this->empresas['DURASOL'];

                if ($presentacion && isset($this->productos[$presentacion]) && $cantidad > 0) {
                    $total = $total ?? $cantidad * (float) $precio;
                    $credito = min((float) ($credito ?? 0), $total);
                    $itemId = DB::table('liquidacion_items')->insertGetId([
                        'liquidacion_id' => $liquidacion->id, 'cliente_id' => $clienteId, 'empresa_id' => $empresaId,
                        'producto_id' => $this->productos[$presentacion], 'cantidad' => (int) $cantidad,
                        // Se respeta el total del Excel; el precio sale de total / cantidad.
                        'precio' => round($total / $cantidad, 2), 'total' => round($total, 2),
                        // Precio de compra de la hoja VENTAS (columna P); si falta, el costo vigente a la fecha.
                        'costo_unitario' => $costoExcel ?: $this->costos->costoUnitario($empresaId, $this->productos[$presentacion], $fecha),
                        'vacios_devueltos' => (int) ($balones ?? 0), 'metodo_pago' => MetodoPago::Efectivo->value,
                        'monto_credito' => round($credito, 2), 'orden' => $orden++, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if ($credito > 0) {
                        $creditos[$clienteId][] = [$fecha, $itemId, $liquidacion->id, round($credito, 2)];
                    }
                }
                if ((float) $cobranza > 0) {
                    $cobranzaId = DB::table('liquidacion_cobranzas')->insertGetId([
                        'liquidacion_id' => $liquidacion->id, 'cliente_id' => $clienteId, 'monto' => round((float) $cobranza, 2),
                        'metodo_pago' => MetodoPago::Efectivo->value, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $pagos[$clienteId][] = [$fecha, $cobranzaId, round((float) $cobranza, 2)];
                }
            }
            $service->recalcular($liquidacion);
        }

        $this->cuentasPorCobrar($creditos, $pagos);
    }

    /**
     * Genera las cuentas por cobrar históricas y les aplica las cobranzas del Excel
     * en orden (lo más antiguo primero). Lo que excede la deuda se ignora (eran deudas previas a 2026).
     */
    private function cuentasPorCobrar(array $creditos, array $pagos): void
    {
        foreach ($creditos as $clienteId => $lista) {
            usort($lista, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $cuentas = [];
            foreach ($lista as [$fecha, $itemId, $liquidacionId, $monto]) {
                $cuentas[] = ['id' => DB::table('cuentas_por_cobrar')->insertGetId([
                    'cliente_id' => $clienteId, 'liquidacion_id' => $liquidacionId, 'liquidacion_item_id' => $itemId,
                    'fecha' => $fecha, 'monto' => $monto, 'saldo' => $monto, 'estado' => EstadoCuenta::Pendiente->value,
                    'observaciones' => 'Crédito importado del Excel', 'created_at' => now(), 'updated_at' => now(),
                ]), 'fecha' => $fecha, 'saldo' => $monto];
            }

            $listaPagos = $pagos[$clienteId] ?? [];
            usort($listaPagos, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            foreach ($listaPagos as [$fechaPago, $cobranzaId, $monto]) {
                foreach ($cuentas as &$cuenta) {
                    if ($monto <= 0.004) {
                        break;
                    }
                    if ($cuenta['saldo'] <= 0.004 || $cuenta['fecha'] > $fechaPago) {
                        continue;
                    }
                    $aplicado = round(min($monto, $cuenta['saldo']), 2);
                    DB::table('cobranzas')->insert([
                        'cuenta_por_cobrar_id' => $cuenta['id'], 'cliente_id' => $clienteId, 'fecha' => $fechaPago, 'monto' => $aplicado,
                        'metodo_pago' => MetodoPago::Efectivo->value, 'liquidacion_cobranza_id' => $cobranzaId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $cuenta['saldo'] = round($cuenta['saldo'] - $aplicado, 2);
                    $monto = round($monto - $aplicado, 2);
                }
                unset($cuenta);
            }

            foreach ($cuentas as $cuenta) {
                DB::table('cuentas_por_cobrar')->where('id', $cuenta['id'])->update([
                    'saldo' => max(0, $cuenta['saldo']),
                    'estado' => $cuenta['saldo'] <= 0.004 ? EstadoCuenta::Pagada->value : EstadoCuenta::Pendiente->value,
                ]);
            }
        }
    }

    /** FISE por chofer y día → se agregan a su liquidación de esa fecha. */
    private function fises(): void
    {
        /** @var LiquidacionService $service */
        $service = app(LiquidacionService::class);
        $afectadas = [];
        $sinLiquidacion = 0;
        foreach ($this->leer('fises.json') as [$fecha, $alias, $valor, $cantidad]) {
            $chofer = $this->chofer($alias);
            if (! $chofer) {
                $sinLiquidacion++;

                continue;
            }
            // Si ese día el chofer no tuvo ventas en la hoja VENTAS, se crea una liquidación solo con sus FISE
            // (así el total de FISE por día coincide con la hoja CAJA GNRAL).
            $liquidacion = Liquidacion::where('chofer_id', $chofer->id)->where('fecha_venta', $fecha)->first()
                ?? tap(Liquidacion::create([
                    'codigo' => 'TMP-'.Str::random(10), 'fecha_venta' => $fecha, 'fecha_liquidacion' => Carbon::parse($fecha)->addDay(),
                    'chofer_id' => $chofer->id, 'vehiculo_id' => $chofer->vehiculo_id,
                    'tipo' => in_array($chofer->tipo, [TipoChofer::Ruta, TipoChofer::Almacen], true) ? $chofer->tipo : TipoChofer::Local,
                    'estado' => EstadoLiquidacion::Cerrada, 'historico' => true, 'cerrada_at' => Carbon::parse($fecha)->addDay()->setTime(18, 0),
                    'observaciones' => 'Importada del Excel: el chofer entregó FISE este día pero no tiene ventas registradas en la hoja VENTAS.',
                ]), fn ($l) => $l->updateQuietly(['codigo' => $service->codigoPara($l->id)]));
            $liquidacion->fises()->create(['valor' => $valor, 'cantidad' => $cantidad, 'subtotal' => $valor * $cantidad]);
            $afectadas[$liquidacion->id] = $liquidacion;
        }
        foreach ($afectadas as $liquidacion) {
            $service->recalcular($liquidacion);
        }
        if ($sinLiquidacion) {
            $this->command?->warn("FISE de responsables desconocidos (no importados): {$sinLiquidacion}");
        }
    }

    /**
     * Parte de logística del 14/09/2026 (archivo "14 de Setiembre del 2026": hojas LLENOS y VACÍOS).
     * El stock inicial se registra como ajuste de inventario del día anterior.
     */
    private function parte14Setiembre(array $inst): void
    {
        $almacen = app(AlmacenService::class);
        $inicial = Parte::create(['fecha' => '2026-09-13', 'estado' => Parte::CERRADO, 'observaciones' => 'Stock inicial importado del Excel de logística.', 'cerrado_at' => now()]);
        $almacen->ajustarAConteo($inicial, [
            'lleno_s10' => 685, 'lleno_s45' => 64, 'lleno_m10' => 33, 'cambio_s10' => 42, 'cambio_s45' => 0, 'cambio_m10' => 3,
            'plomo_s10' => 1547, 'plomo_s45' => 17, 'color_s10' => 749, 'color_s45' => 85,
        ]);

        $d = $inst['62170833'];  // ABUELO · Durasol
        $fredy = $inst['62173099'];
        $abel = $inst['62170835'];
        $hvca = $inst['62174003']; // placas CET-858 y W6N-923 · Llamazul
        $fila = fn (string $bloque, ?string $placa, string $resp, ?string $lugar, array $cant, ?Instalacion $i = null) => $cant + [
            'bloque' => $bloque, 'placa' => $placa, 'responsable' => $this->normalizarChofer($resp), 'lugar' => $lugar,
            'instalacion_id' => $i?->id, 'empresa_id' => $i?->empresa_id,
        ];
        $LI = ParteFila::LLENO_INGRESO;
        $LS = ParteFila::LLENO_SALIDA;
        $VI = ParteFila::VACIO_INGRESO;
        $VS = ParteFila::VACIO_SALIDA;

        $filas = [
            // LLENOS · INGRESO: retornos de choferes, cambios y camiones de planta.
            $fila($LI, null, 'JORGUE', 'LOCAL', ['s10' => 23]),
            $fila($LI, null, 'RONALD', 'LOCAL', ['s10' => 2]),
            $fila($LI, null, 'MIZAEL', 'LOCAL', ['s10' => 32]),
            $fila($LI, null, 'URBANO', 'LOCAL', ['s10' => 7]),
            $fila($LI, null, 'CAMBIOS', 'LOCAL', ['cambio_s10' => 2]),
            $fila($LI, null, 'FREDY', 'LOCAL', ['s10' => 4, 'cambio_s10' => 1]),
            $fila($LI, 'W6D-892', 'ABULO', 'PLANTA', ['s10' => 420], $d),
            $fila($LI, 'W6D-892', 'ABUELO', 'PLANTA', ['m10' => 420], $d),
            $fila($LI, 'BRU-782', 'FREDY', 'PLANTA', ['s10' => 465], $fredy),
            $fila($LI, 'W6N-923', 'MILTON', 'PLANTA', ['s10' => 500, 's45' => 15], $hvca),
            $fila($LI, 'W6F-740', 'ABEL', 'PLANTA', ['s10' => 450], $abel),
            $fila($LI, 'CET-858', 'RUFINO', 'PLANTA', ['s10' => 720], $hvca),
            $fila($LI, 'W6N-923', 'MILTON', 'PLANTA', ['s45' => 120], $hvca),
            // LLENOS · SALIDA
            $fila($LS, null, 'MIZAEL', 'LOCAL', ['s10' => 76]),
            $fila($LS, null, 'RONALD', 'LOCAL', ['s10' => 160]),
            $fila($LS, null, 'URBANO', 'LOCAL', ['s10' => 71]),
            $fila($LS, null, 'URBANO', 'LOCAL', ['s10' => 46]),
            $fila($LS, null, 'JORGUE', 'LOCAL', ['s10' => 86]),
            $fila($LS, null, 'LOCAL', 'LOCAL', ['s10' => 2]),
            $fila($LS, null, 'AGUILAR', 'MINA', ['s45' => 50]),
            $fila($LS, null, 'COTRINA', 'RUTA', ['s10' => 60, 'm10' => 360]),
            $fila($LS, null, 'JENY GASPAR', 'RUTA', ['s10' => 103]),
            $fila($LS, null, 'CHIWUA', 'RUTA', ['s10' => 229]),
            $fila($LS, null, 'YELSIN', 'RUTA', ['s10' => 130, 's45' => 30]),
            $fila($LS, null, 'PNP-PENAL', 'RUTA', ['s45' => 1]),
            $fila($LS, null, 'PNP-HYO', 'RUTA', ['s45' => 1]),
            $fila($LS, null, 'EJERCITO', 'CHILCA', ['s45' => 2]),
            $fila($LS, null, 'MILTON', 'RUTA', ['s10' => 500, 's45' => 15]),
            $fila($LS, null, 'JHON-H', 'RUTA', ['s10' => 70]),
            // VACÍOS · INGRESO (plomo = s10/s45, color = color_s10/color_s45)
            $fila($VI, null, 'URBANO', 'LOCAL', ['s10' => 58, 'color_s10' => 4]),
            $fila($VI, null, 'URBANO', 'LOCAL', ['s10' => 81, 'color_s10' => 4]),
            $fila($VI, null, 'RONALD', 'LOCAL', ['s10' => 201, 'color_s10' => 17]),
            $fila($VI, null, 'MIZAEL', 'LOCAL', ['s10' => 40, 'color_s10' => 4]),
            $fila($VI, null, 'JORGUE', 'LOCAL', ['s10' => 52, 'color_s10' => 11]),
            $fila($VI, null, 'AGUILAR', 'LOCAL', ['s10' => 428, 'color_s10' => 22]),
            $fila($VI, null, 'AGUILAR', 'MINA', ['s45' => 57]),
            $fila($VI, null, 'FREDY', 'LOCAL', ['s10' => 365, 'color_s10' => 34]),
            $fila($VI, null, 'ABEL', 'LOCAL', ['s10' => 267, 'color_s10' => 47]),
            $fila($VI, null, 'JENY GASPAR', 'RUTA', ['s10' => 97, 'color_s10' => 1]),
            $fila($VI, null, 'CHIWUA', 'RUTA', ['s10' => 214, 'color_s10' => 15]),
            $fila($VI, null, 'PNP-PENAL', 'RUTA', ['color_s45' => 1]),
            $fila($VI, null, 'PNP-HYO', 'RUTA', ['color_s45' => 1]),
            $fila($VI, null, 'EJERCITO', 'CHILCA', ['color_s45' => 2]),
            $fila($VI, null, 'JHON-H', 'RUTA', ['s10' => 67, 'color_s10' => 8]),
            $fila($VI, null, 'EXACTO', 'CANJE', ['s10' => 250, 's45' => 2]),
            // VACÍOS · SALIDA a planta y canje
            $fila($VS, 'W6D-892', 'ABUELO', 'PLANTA', ['s10' => 380, 'color_s10' => 40], $d),
            $fila($VS, 'W6D-892', 'ABUELO', 'PLANTA', ['s10' => 395, 'color_s10' => 25], $d),
            $fila($VS, 'BRU-782', 'FREDY', 'PLANTA', ['s10' => 440, 'color_s10' => 25], $fredy),
            $fila($VS, 'W6F-740', 'ABEL', 'PLANTA', ['s10' => 408, 'color_s10' => 42], $abel),
            $fila($VS, 'CET-858', 'RUFINO', 'PLANTA', ['s10' => 650, 'color_s10' => 70], $hvca),
            $fila($VS, 'W6N-923', 'MILTON', 'PLANTA', ['s10' => 460, 's45' => 15, 'color_s10' => 40], $hvca),
            $fila($VS, null, 'EXACTO', 'CANJE', ['color_s10' => 250, 'color_s45' => 2]),
        ];

        $almacen->guardar(Carbon::parse('2026-09-14'), $filas, 'Importado del Excel de logística del 14/09/2026.');
        Parte::where('fecha', '2026-09-14')->update(['estado' => Parte::CERRADO, 'cerrado_at' => now()]);
    }

    /**
     * Hojas B.LLENO y B.VACÍO del 25/09 (Excel de rentabilidad): stock inicial por conteo del 24/09
     * y los movimientos del día. Reproduce el "STOCK FINAL" del Excel.
     */
    private function parte25Setiembre(array $inst): void
    {
        $almacen = app(AlmacenService::class);
        $conteo = Parte::create(['fecha' => '2026-09-24', 'estado' => Parte::ABIERTO, 'observaciones' => 'Conteo de almacén según el Excel de rentabilidad (B.LLENO / B.VACIO).']);
        $almacen->ajustarAConteo($conteo, [
            'lleno_s10' => 1498, 'lleno_s45' => 101, 'lleno_m10' => 26, 'cambio_s10' => 29, 'cambio_s45' => 0, 'cambio_m10' => 3,
            'plomo_s10' => 920, 'plomo_s45' => 14, 'color_s10' => 544, 'color_s45' => 4,
        ]);

        $fila = fn (string $bloque, ?string $placa, string $resp, ?string $lugar, array $cant, ?Instalacion $i = null) => $cant + [
            'bloque' => $bloque, 'placa' => $placa, 'responsable' => $resp, 'lugar' => $lugar,
            'instalacion_id' => $i?->id, 'empresa_id' => $i?->empresa_id,
        ];
        $LI = ParteFila::LLENO_INGRESO;
        $LS = ParteFila::LLENO_SALIDA;
        $VI = ParteFila::VACIO_INGRESO;
        $VS = ParteFila::VACIO_SALIDA;

        $filas = [
            $fila($LI, 'W2S-907', 'ABUELO', 'PLANTA', ['s10' => 417], $inst['62170833']),
            $fila($LI, 'BWF-817', 'RUFINO', 'PLANTA', ['s10' => 420], $inst['62170831']),
            $fila($LI, 'W6F-740', 'ABEL', 'PLANTA', ['s10' => 450], $inst['62170835']),
            $fila($LI, 'W6N-923', 'ABUELO', 'PLANTA', ['s10' => 720], $inst['62174003']),
            $fila($LI, null, 'CAMBIOS', 'LOCAL', ['cambio_s10' => 5]),
            $fila($LS, null, 'MISAEL', 'LOCAL', ['s10' => 28]),
            $fila($LS, null, 'URBANO', 'LOCAL', ['s10' => 112, 's45' => 2]),
            $fila($LS, null, 'JORGE', 'LOCAL', ['s10' => 168]),
            $fila($LS, null, 'RONALD', 'LOCAL', ['s10' => 124]),
            $fila($LS, null, 'ZADITH', 'LOCAL', ['s10' => 111]),
            $fila($LS, null, 'ABEL', 'RUTA', ['s10' => 450]),
            $fila($LS, null, 'RUFINO', 'RUTA', ['s10' => 320, 's45' => 24]),
            $fila($LS, null, 'CAMBIOS', 'LOCAL', ['s10' => 5]),
            $fila($VI, null, 'MISAEL', 'LOCAL', ['s10' => 26, 'color_s10' => 2]),
            $fila($VI, null, 'URBANO', 'LOCAL', ['s10' => 106, 'color_s10' => 6, 's45' => 2]),
            $fila($VI, null, 'JORGE', 'LOCAL', ['s10' => 139, 'color_s10' => 9]),
            $fila($VI, null, 'RONALD', 'LOCAL', ['s10' => 108, 'color_s10' => 16]),
            $fila($VI, null, 'COTRINA', 'RUTA', ['s10' => 242, 'color_s10' => 32]),
            $fila($VI, null, 'JENY GASPAR', 'RUTA', ['s10' => 57, 'color_s10' => 1]),
            $fila($VI, null, 'BRAYAN GAS', 'RUTA', ['color_s45' => 1]),
            $fila($VI, null, 'JUAN ARPA', 'RUTA', ['s10' => 41, 'color_s10' => 9]),
            $fila($VI, null, 'EJERCITO', 'CHILCA', ['s45' => 2]),
            $fila($VI, null, 'PNP-CHILCA', 'CHILCA', ['s45' => 1]),
            $fila($VI, null, 'ABEL', 'RUTA', ['s10' => 420, 'color_s10' => 30]),
            $fila($VI, null, 'AGUILAR', 'RUTA', ['s10' => 400, 'color_s10' => 50]),
            $fila($VI, null, 'MOVIL CHINO', 'CANJE', ['s10' => 19]),
            $fila($VI, null, 'PLANTA MOVIL', 'CANJE', ['s10' => 200]),
            $fila($VS, 'W2S-907', 'ABUELO', 'PLANTA', ['s10' => 379, 'color_s10' => 38], $inst['62170833']),
            $fila($VS, 'BWF-817', 'RUFINO', 'PLANTA', ['s10' => 380, 'color_s10' => 40], $inst['62170831']),
            $fila($VS, 'W6F-740', 'ABEL', 'PLANTA', ['s10' => 420, 'color_s10' => 30], $inst['62170835']),
            $fila($VS, 'W6N-923', 'ABUELO', 'PLANTA', ['s10' => 684, 'color_s10' => 36], $inst['62174003']),
            $fila($VS, null, 'MOVIL CHINO', 'CANJE', ['color_s10' => 19]),
            $fila($VS, null, 'PLANTA MOVIL', 'CANJE', ['color_s10' => 200]),
        ];

        $almacen->guardar(Carbon::parse('2026-09-25'), $filas, 'Importado del Excel de rentabilidad (B.LLENO / B.VACIO del 25/09).');
    }

    /** Depósitos del 25/09 (RESUMEN GNRAL) y totales diarios de agosto (CAJA GNRAL). No mueven la caja actual. */
    private function depositos(): void
    {
        $cuentas = CuentaBancaria::get()->keyBy(fn ($c) => $c->banco.'|'.$c->alias);
        foreach ($this->leer('depositos.json') as [$fecha, $responsable, $banco, $empresa, $quien, $monto]) {
            $cuenta = $cuentas[$banco.'|'.$empresa] ?? CuentaBancaria::firstOrCreate(['banco' => $banco, 'alias' => $empresa], ['moneda' => 'PEN', 'activo' => true]);
            Deposito::create([
                'fecha' => $fecha, 'cuenta_bancaria_id' => $cuenta->id, 'empresa_id' => $this->empresas[$empresa] ?? null,
                'chofer_id' => $this->chofer($responsable)?->id, 'depositante' => $quien, 'monto' => $monto,
                'observaciones' => 'Importado del Excel (RESUMEN GNRAL).',
            ]);
        }
        $bcpDurasol = $cuentas['BCP|DURASOL'] ?? null;
        foreach ($this->leer('caja_agosto.json') as [$fecha, $varios, $personal, $deposito]) {
            if ($deposito > 0) {
                Deposito::create([
                    'fecha' => $fecha, 'cuenta_bancaria_id' => $bcpDurasol?->id, 'monto' => $deposito,
                    'observaciones' => 'Total de depósitos del día importado del Excel (CAJA GNRAL · agosto). Gastos varios del día: '.soles($varios ?? 0).'.',
                ]);
            }
        }
    }

    /** Compras en planta de agosto (hoja STOCK): no hay parte diario de esas fechas. */
    private function comprasHistoricas(): void
    {
        $filas = [];
        foreach ($this->leer('compras_agosto.json') as [$fecha, $porEmpresa]) {
            foreach ($porEmpresa as $empresa => $cantidades) {
                foreach ($cantidades as $codigo => $cantidad) {
                    if ($cantidad > 0 && isset($this->empresas[$empresa], $this->productos[$codigo])) {
                        $filas[] = ['fecha' => $fecha, 'empresa_id' => $this->empresas[$empresa], 'producto_id' => $this->productos[$codigo],
                            'cantidad' => (int) $cantidad, 'origen' => 'excel', 'created_at' => now(), 'updated_at' => now()];
                    }
                }
            }
        }
        DB::table('compras_planta')->insert($filas);
    }

    private function leer(string $archivo): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/'.$archivo)), true, flags: JSON_THROW_ON_ERROR);
    }
}
