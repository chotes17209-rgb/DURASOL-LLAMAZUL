<?php

namespace Database\Seeders;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoDespacho;
use App\Enums\EstadoGuia;
use App\Enums\EstadoLiquidacion;
use App\Enums\EstadoStock;
use App\Enums\MetodoPago;
use App\Enums\TipoChofer;
use App\Enums\TipoMovimientoManual;
use App\Models\Canje;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\CuentaBancaria;
use App\Models\Deposito;
use App\Models\Despacho;
use App\Models\Empresa;
use App\Models\Guia;
use App\Models\Instalacion;
use App\Models\Liquidacion;
use App\Models\MovimientoStockManual;
use App\Models\PrecioCompra;
use App\Models\Producto;
use App\Models\Vehiculo;
use App\Services\LiquidacionService;
use App\Services\LogisticaService;
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
 *  - STOCK: compras de agosto (guías históricas, no mueven stock).
 *  - B.LLENO / B.VACIO: foto del almacén al 25/09 (stock inicial + movimientos de ese día).
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

    public function run(): void
    {
        AuditLogger::withoutAuditing(function () {
            DB::disableQueryLog();
            $data = $this->leer('data.json');

            $this->productos = Producto::pluck('id', 'codigo')->all();
            $this->empresas = Empresa::pluck('id', 'nombre')->all();

            $this->vehiculosYChoferes($data['placas']);
            $instalaciones = $this->instalacionesYPreciosCompra($data['precios_compra']);
            $this->clientes($data['clientes']);
            $this->command?->info('Clientes y precios importados: '.count($this->clientesPorCodigo));

            $this->ventas();
            $this->command?->info('Ventas importadas: '.Liquidacion::count().' liquidaciones');

            $this->fises();
            $this->comprasAgosto($instalaciones);
            $this->fotoAlmacen25Setiembre($instalaciones);
            $this->depositos();
            $this->command?->info('Stock, guías, despachos y depósitos importados.');
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
            'ZADIT' => 'ZADITH',
            '' => 'OTROS',
            default => $alias,
        };
    }

    /** Una instalación por empresa (el Excel no trae los códigos de 8 dígitos: completarlos). */
    private function instalacionesYPreciosCompra(array $precios): array
    {
        $instalaciones = [];
        $datos = [
            'DURASOL' => ['codigo' => '10000001', 'chofer' => 'ABUELO', 'placa' => 'W2S-907'],
            'LLAMAZUL' => ['codigo' => '20000001', 'chofer' => 'ABEL', 'placa' => 'W6F-740'],
        ];
        foreach ($datos as $empresa => $d) {
            $instalacion = Instalacion::firstOrCreate(['codigo' => $d['codigo']], [
                'nombre' => "Instalación principal {$empresa}",
                'empresa_id' => $this->empresas[$empresa],
                'chofer_id' => $this->chofer($d['chofer'])?->id,
                'vehiculo_id' => $this->vehiculos[$d['placa']] ?? null,
                'activo' => true,
                'observaciones' => 'Creada al importar el Excel: reemplazar por el código real de 8 dígitos de la instalación Solgas.',
            ]);
            $instalaciones[$empresa] = $instalacion;
            foreach ($precios[$empresa] ?? [] as $codigo => $precio) {
                PrecioCompra::firstOrCreate(
                    ['instalacion_id' => $instalacion->id, 'producto_id' => $this->productos[$codigo], 'vigente_desde' => self::FECHA_PRECIOS],
                    ['empresa_id' => $instalacion->empresa_id, 'precio' => $precio, 'motivo' => 'Importado del Excel (DATA · precios de compra)'],
                );
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

    /** Compras de agosto (hoja STOCK): guías históricas que no mueven el stock actual. */
    private function comprasAgosto(array $instalaciones): void
    {
        foreach ($this->leer('compras_agosto.json') as [$fecha, $porEmpresa]) {
            foreach ($porEmpresa as $empresa => $productos) {
                if (! $productos) {
                    continue;
                }
                $instalacion = $instalaciones[$empresa];
                $guia = Guia::create([
                    'numero_guia' => 'HIST-'.str_replace('-', '', $fecha).'-'.substr($empresa, 0, 3),
                    'empresa_id' => $instalacion->empresa_id, 'instalacion_id' => $instalacion->id,
                    'fecha_salida' => $fecha, 'fecha_recepcion' => $fecha, 'estado' => EstadoGuia::Recibida, 'historico' => true,
                    'observaciones' => 'Compra de agosto importada del Excel (hoja STOCK). No afecta el stock actual.',
                ]);
                foreach ($productos as $codigo => $cantidad) {
                    $precio = PrecioCompra::where('instalacion_id', $instalacion->id)->where('producto_id', $this->productos[$codigo])->value('precio') ?? 0;
                    $guia->detalles()->create([
                        'producto_id' => $this->productos[$codigo], 'cantidad_guia' => $cantidad, 'precio_compra' => $precio,
                        'vacios_enviados' => $cantidad, 'llenos_recibidos' => $cantidad,
                    ]);
                }
            }
        }
    }

    /**
     * Hojas B.LLENO y B.VACIO del 25/09: stock inicial (24/09) y todo el movimiento de ese día.
     * El resultado reproduce el "STOCK FINAL" del Excel (S10 llenos 2187, vacíos plomo 815, etc.).
     */
    private function fotoAlmacen25Setiembre(array $instalaciones): void
    {
        /** @var LogisticaService $logistica */
        $logistica = app(LogisticaService::class);
        $durasol = $this->empresas['DURASOL'];
        $llamazul = $this->empresas['LLAMAZUL'];
        $p = $this->productos;
        $inicial = '2026-09-24';
        $dia = '2026-09-25';

        $manual = function (string $fecha, TipoMovimientoManual $tipo, string $sentido, ?int $empresa, string $producto, EstadoStock $estado, int $cantidad, string $referencia) use ($logistica, $p) {
            $mov = MovimientoStockManual::create([
                'fecha' => $fecha, 'tipo' => $tipo, 'sentido' => $sentido, 'empresa_id' => $empresa, 'producto_id' => $p[$producto],
                'estado' => $estado, 'cantidad' => $cantidad, 'referencia' => $referencia, 'observaciones' => 'Importado del Excel (B.LLENO / B.VACIO).',
            ]);
            $logistica->aplicarManual($mov);
        };

        // Stock inicial. El Excel no separa por empresa: se asigna a Durasol, salvo lo que Llamazul necesita para sus salidas del día.
        $ini = TipoMovimientoManual::StockInicial;
        $manual($inicial, $ini, 'entrada', $durasol, 'S10', EstadoStock::Lleno, 1498, 'Stock inicial llenos');
        $manual($inicial, $ini, 'entrada', $durasol, 'S45', EstadoStock::Lleno, 77, 'Stock inicial llenos');
        $manual($inicial, $ini, 'entrada', $llamazul, 'S45', EstadoStock::Lleno, 24, 'Stock inicial llenos');
        $manual($inicial, $ini, 'entrada', $durasol, 'M10', EstadoStock::Lleno, 26, 'Stock inicial llenos');
        $manual($inicial, $ini, 'entrada', $durasol, 'C10', EstadoStock::Lleno, 308, 'Stock inicial Contigas');
        $manual($inicial, $ini, 'entrada', $durasol, 'C45', EstadoStock::Lleno, 120, 'Stock inicial Contigas');
        $manual($inicial, $ini, 'entrada', $durasol, 'S10', EstadoStock::Cambio, 29, 'Stock inicial cambios');
        $manual($inicial, $ini, 'entrada', $durasol, 'M10', EstadoStock::Cambio, 3, 'Stock inicial cambios');
        $manual($inicial, $ini, 'entrada', null, 'S10', EstadoStock::Vacio, 920, 'Stock inicial vacíos plomo');
        $manual($inicial, $ini, 'entrada', null, 'S45', EstadoStock::Vacio, 14, 'Stock inicial vacíos plomo');
        $manual($inicial, $ini, 'entrada', null, 'S10', EstadoStock::Color, 544, 'Stock inicial vacíos de color');
        $manual($inicial, $ini, 'entrada', null, 'S45', EstadoStock::Color, 4, 'Stock inicial vacíos de color');

        // Vacíos que dejaron clientes y choferes de ruta en el local (B.VACIO · ingreso).
        foreach ([['COTRINA', 242, 32, 0, 0], ['JENY GASPAR', 57, 1, 0, 0], ['BRAYAN GAS', 0, 0, 0, 1], ['JUAN ARPA', 41, 9, 0, 0], ['EJERCITO', 0, 0, 2, 0],
            ['PNP-CHILCA', 0, 0, 1, 0], ['ABEL (ruta)', 420, 30, 0, 0], ['AGUILAR (ruta)', 400, 50, 0, 0]] as [$ref, $plomo10, $color10, $plomo45, $color45]) {
            foreach ([['S10', EstadoStock::Vacio, $plomo10], ['S10', EstadoStock::Color, $color10], ['S45', EstadoStock::Vacio, $plomo45], ['S45', EstadoStock::Color, $color45]] as [$prod, $estado, $cant]) {
                if ($cant > 0) {
                    $manual($dia, TipoMovimientoManual::IngresoVacios, 'entrada', null, $prod, $estado, $cant, $ref);
                }
            }
        }

        // Canjes de colores por plomos.
        foreach ([['MOVIL CHINO', 19], ['PLANTA MOVIL', 200]] as [$contraparte, $cantidad]) {
            $canje = Canje::create(['fecha' => $dia, 'contraparte' => $contraparte, 'producto_id' => $p['S10'], 'colores_entregados' => $cantidad, 'plomos_recibidos' => $cantidad, 'observaciones' => 'Importado del Excel (B.VACIO).']);
            $logistica->aplicarCanje($canje);
        }

        // Guías del día: salieron vacíos y regresaron llenos (B.VACIO salida / B.LLENO ingreso).
        foreach ([['DURASOL', 'ABUELO', 'W2S-907', 379, 38], ['DURASOL', 'RUFINO', 'BWF-817', 380, 40], ['LLAMAZUL', 'ABEL', 'W6F-740', 420, 30], ['LLAMAZUL', 'ABUELO', 'W6N-923', 684, 36]] as $i => [$empresa, $alias, $placa, $plomo, $color]) {
            $instalacion = $instalaciones[$empresa];
            $guia = Guia::create([
                'numero_guia' => 'EXCEL-20260925-'.($i + 1), 'empresa_id' => $instalacion->empresa_id, 'instalacion_id' => $instalacion->id,
                'vehiculo_id' => $this->vehiculos[$placa] ?? null, 'chofer_id' => $this->chofer($alias)?->id,
                'fecha_salida' => $dia, 'fecha_recepcion' => $dia, 'estado' => EstadoGuia::Recibida,
                'observaciones' => 'Importada del Excel: el número de guía real no figura en la hoja.',
            ]);
            $precio = PrecioCompra::where('instalacion_id', $instalacion->id)->where('producto_id', $p['S10'])->value('precio') ?? 0;
            $guia->detalles()->create([
                'producto_id' => $p['S10'], 'cantidad_guia' => $plomo + $color, 'precio_compra' => $precio,
                'vacios_enviados' => $plomo, 'colores_enviados' => $color, 'llenos_recibidos' => $plomo + $color,
            ]);
            $logistica->aplicarGuia($guia);
        }

        // Despachos del día. Durasol (reparto local, ya retornaron) y Llamazul (ruta, por liquidar).
        $despachos = [
            ['MISAEL', $durasol, ['S10' => 28], [26, 2, 0, 0], true],
            ['URBANO', $durasol, ['S10' => 112, 'S45' => 2], [106, 6, 2, 0], true],
            ['JORGE', $durasol, ['S10' => 168], [139, 9, 0, 0], true],
            ['RONALD', $durasol, ['S10' => 124], [108, 16, 0, 0], true],
            ['ZADITH', $durasol, ['S10' => 111, 'C45' => 4], [0, 0, 0, 0], true],
            ['ABEL', $llamazul, ['S10' => 450], null, false],
            ['RUFINO', $llamazul, ['S10' => 320, 'S45' => 24], null, false],
        ];
        foreach ($despachos as [$alias, $empresaId, $salidas, $vacios, $retornado]) {
            $chofer = $this->chofer($alias);
            $despacho = Despacho::create([
                'fecha' => $dia, 'chofer_id' => $chofer->id, 'vehiculo_id' => $chofer->vehiculo_id, 'vuelta' => 1,
                'tipo' => $chofer->tipo === TipoChofer::Almacen ? TipoChofer::Almacen : $chofer->tipo,
                'estado' => $retornado ? EstadoDespacho::Retornado : EstadoDespacho::EnRuta,
                'hora_salida' => '07:30', 'hora_retorno' => $retornado ? '18:00' : null,
                'observaciones' => 'Importado del Excel (B.LLENO: '.($retornado ? 'salida de mercadería liquidada' : 'salida por liquidar').').',
            ]);
            foreach ($salidas as $codigo => $cantidad) {
                $esS10 = $codigo === 'S10';
                $despacho->detalles()->create([
                    'empresa_id' => $empresaId, 'producto_id' => $p[$codigo], 'llenos_salida' => $cantidad,
                    'vacios_retorno' => $vacios ? ($esS10 ? $vacios[0] : ($codigo === 'S45' ? $vacios[2] : 0)) : 0,
                    'colores_retorno' => $vacios ? ($esS10 ? $vacios[1] : ($codigo === 'S45' ? $vacios[3] : 0)) : 0,
                ]);
            }
            $logistica->aplicarDespacho($despacho);
        }

        // Cambio recibido en el local (Llamazul): entra un fallado y sale un lleno a cambio.
        $manual($dia, TipoMovimientoManual::Ajuste, 'entrada', $llamazul, 'S10', EstadoStock::Cambio, 5, 'Cambios recibidos en LOCAL');
        $manual($dia, TipoMovimientoManual::Ajuste, 'salida', $llamazul, 'S10', EstadoStock::Lleno, 5, 'Llenos entregados por cambios en LOCAL');
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

    private function leer(string $archivo): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/'.$archivo)), true, flags: JSON_THROW_ON_ERROR);
    }
}
