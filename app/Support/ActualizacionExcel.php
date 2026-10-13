<?php

namespace App\Support;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoLiquidacion;
use App\Enums\MetodoPago;
use App\Enums\TipoChofer;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\CuentaBancaria;
use App\Models\Deposito;
use App\Models\Instalacion;
use App\Models\Liquidacion;
use App\Models\PrecioCompra;
use App\Models\PrecioVenta;
use App\Services\CostoService;
use App\Services\CuentaService;
use App\Services\LiquidacionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Actualiza una base ya cargada con un Excel de rentabilidad más reciente
 * (archivo JSON en database/seeders/data, generado a partir del Excel del cliente):
 *  - ventas nuevas (hoja VENTAS) como liquidaciones históricas, con créditos y cobranzas;
 *  - variaciones de precio de venta por cliente (hoja DATA) desde el día en que se aplicaron;
 *  - variaciones del precio de compra de la empresa (columna PRECIO DE COMPRA de VENTAS);
 *  - FISE y depósitos del día que muestra la hoja RESUMEN GNRAL.
 *
 * Se puede ejecutar más de una vez: no duplica filas ya importadas y no toca las
 * liquidaciones registradas a mano en el sistema (esas fechas y choferes se respetan).
 */
class ActualizacionExcel
{
    private const ALIAS = ['RONAL' => 'RONALD', 'JORGUE' => 'JORGE', 'MIZAEL' => 'MISAEL', 'ABULO' => 'ABUELO', 'ZADIT' => 'ZADITH', '' => 'OTROS'];

    /** @var array<string, int> */
    private array $productos;

    /** @var array<string, int> */
    private array $empresas;

    /** @var array<string, ?Chofer> */
    private array $choferes = [];

    /** Resumen de lo hecho, para el registro de la migración. */
    public array $resumen = ['liquidaciones' => 0, 'filas' => 0, 'omitidas_manual' => [], 'precios' => 0, 'precios_compra' => 0, 'fise' => 0, 'depositos' => 0];

    public function __construct(
        private readonly LiquidacionService $liquidaciones,
        private readonly CuentaService $cuentas,
        private readonly CostoService $costos,
    ) {
        $this->productos = DB::table('productos')->pluck('id', 'codigo')->all();
        $this->empresas = DB::table('empresas')->pluck('id', 'nombre')->all();
    }

    public static function aplicar(string $archivo): array
    {
        $ruta = is_file($archivo) ? $archivo : database_path('seeders/data/'.$archivo);
        $datos = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        $yo = app(self::class);
        AuditLogger::withoutAuditing(function () use ($yo, $datos) {
            DB::transaction(function () use ($yo, $datos) {
                $yo->ventas($datos['ventas'] ?? [], $datos['fuente'] ?? 'Excel');
                $yo->precios($datos['precios_venta'] ?? [], $datos['fuente'] ?? 'Excel');
                $yo->preciosCompra($datos['precios_compra'] ?? [], $datos['fuente'] ?? 'Excel');
                if (! empty($datos['resumen_dia'])) {
                    $yo->fise($datos['resumen_dia'], $datos['fise_resumen'] ?? []);
                    $yo->depositos($datos['depositos'] ?? []);
                }
            });
        });

        return $yo->resumen;
    }

    /**
     * Filas: fecha, fecha_liq, empresa, codigo, placa, responsable, cliente, presentacion,
     * cantidad, precio, total, balones, credito, cobranza, costo (precio de compra).
     */
    public function ventas(array $filas, string $fuente): void
    {
        // Costo del Excel por fecha, empresa y presentación: el más frecuente (un costo mal digitado no pesa).
        $costos = [];
        foreach ($filas as $f) {
            if (! empty($f[14]) && $f[7]) {
                $k = $f[0].'|'.mb_strtoupper((string) $f[2]).'|'.$f[7];
                $costos[$k][(string) round($f[14], 2)] = ($costos[$k][(string) round($f[14], 2)] ?? 0) + 1;
            }
        }
        $costos = array_map(function ($c) {
            arsort($c);

            return (float) array_key_first($c);
        }, $costos);

        $grupos = [];
        foreach ($filas as $f) {
            if ($f[0]) {
                $grupos[$f[0].'|'.$this->alias($f[5])][] = $f;
            }
        }
        ksort($grupos);

        foreach ($grupos as $clave => $grupo) {
            [$fecha, $alias] = explode('|', $clave);
            $chofer = $this->chofer($alias) ?? $this->chofer('OTROS');
            $existente = Liquidacion::where('chofer_id', $chofer->id)->where('fecha_venta', $fecha)
                ->where('estado', '!=', EstadoLiquidacion::Anulada)->orderByDesc('historico')->first();
            if ($existente && ! $existente->historico) {
                // Ese día ya se liquidó en el sistema: se respeta lo registrado a mano.
                $this->resumen['omitidas_manual'][] = "{$fecha} {$alias}";

                continue;
            }
            $liquidacion = $existente ?? $this->nuevaLiquidacion($fecha, $chofer, $grupo, $fuente);
            $this->agregarFilas($liquidacion, $grupo, $fecha, $chofer, $costos);
        }
    }

    private function nuevaLiquidacion(string $fecha, Chofer $chofer, array $grupo, string $fuente): Liquidacion
    {
        $fechaLiq = collect($grupo)->pluck(1)->filter()->first() ?? Carbon::parse($fecha)->addDay()->toDateString();
        $fechaLiq = min(max($fechaLiq, $fecha), max($fecha, today()->toDateString()));
        $placa = collect($grupo)->pluck(4)->first(fn ($p) => $p && preg_match('/^[A-Z0-9]{3}-\d{3}$/', $p));
        $vehiculo = $placa ? DB::table('vehiculos')->where('placa', $placa)->value('id') : null;

        $liquidacion = Liquidacion::create([
            'codigo' => 'TMP-'.Str::random(10), 'fecha_venta' => $fecha, 'fecha_liquidacion' => $fechaLiq,
            'chofer_id' => $chofer->id, 'vehiculo_id' => $vehiculo ?? $chofer->vehiculo_id,
            'tipo' => in_array($chofer->tipo, [TipoChofer::Ruta, TipoChofer::Almacen], true) ? $chofer->tipo : TipoChofer::Local,
            'estado' => EstadoLiquidacion::Cerrada, 'historico' => true, 'cerrada_at' => Carbon::parse($fechaLiq)->setTime(18, 0),
            'observaciones' => "Importada del Excel ({$fuente}, hoja VENTAS).",
        ]);
        $liquidacion->updateQuietly(['codigo' => $this->liquidaciones->codigoPara($liquidacion->id)]);
        $this->resumen['liquidaciones']++;

        return $liquidacion;
    }

    private function agregarFilas(Liquidacion $liquidacion, array $grupo, string $fecha, Chofer $chofer, array $costos): void
    {
        $orden = (int) $liquidacion->items()->max('orden') + 1;
        $cobranzas = [];
        foreach ($grupo as $f) {
            [, , $empresa, $codigo, , , $nombre, $presentacion, $cantidad, , $total, $balones, $credito, $cobranza] = $f;
            $clienteId = $this->clienteId($codigo, $nombre, $chofer);
            $empresaId = $this->empresas[mb_strtoupper((string) $empresa)] ?? $this->empresas['DURASOL'];

            if ($presentacion && isset($this->productos[$presentacion]) && $cantidad > 0) {
                $productoId = $this->productos[$presentacion];
                $total = round($total ?? $cantidad * (float) $f[9], 2);
                $repetida = DB::table('liquidacion_items')->where('liquidacion_id', $liquidacion->id)->where('cliente_id', $clienteId)
                    ->where('producto_id', $productoId)->where('cantidad', (int) $cantidad)->whereBetween('total', [$total - 0.01, $total + 0.01])->exists();
                if (! $repetida) {
                    $credito = round(min((float) ($credito ?? 0), $total), 2);
                    $itemId = DB::table('liquidacion_items')->insertGetId([
                        'liquidacion_id' => $liquidacion->id, 'cliente_id' => $clienteId, 'empresa_id' => $empresaId,
                        'producto_id' => $productoId, 'cantidad' => (int) $cantidad,
                        'precio' => round($total / $cantidad, 2), 'total' => $total,
                        'costo_unitario' => $costos[$fecha.'|'.mb_strtoupper((string) $empresa).'|'.$presentacion] ?? $this->costos->costoUnitario($empresaId, $productoId, $fecha),
                        'vacios_devueltos' => (int) ($balones ?? 0), 'metodo_pago' => MetodoPago::Efectivo->value,
                        'monto_credito' => $credito, 'orden' => $orden++, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if ($credito > 0) {
                        DB::table('cuentas_por_cobrar')->insert([
                            'cliente_id' => $clienteId, 'liquidacion_id' => $liquidacion->id, 'liquidacion_item_id' => $itemId,
                            'fecha' => $fecha, 'monto' => $credito, 'saldo' => $credito, 'estado' => EstadoCuenta::Pendiente->value,
                            'observaciones' => 'Crédito importado del Excel', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    $this->resumen['filas']++;
                }
            }
            if ((float) $cobranza > 0) {
                $cobranzas[] = [$clienteId, round((float) $cobranza, 2)];
            }
        }

        // Cobranzas del día: se aplican a las deudas más antiguas del cliente (lo que excede la deuda queda registrado sin aplicar).
        foreach ($cobranzas as [$clienteId, $monto]) {
            $ya = DB::table('liquidacion_cobranzas')->where('liquidacion_id', $liquidacion->id)->where('cliente_id', $clienteId)
                ->whereBetween('monto', [$monto - 0.01, $monto + 0.01])->exists();
            if ($ya) {
                continue;
            }
            $cobranzaId = DB::table('liquidacion_cobranzas')->insertGetId([
                'liquidacion_id' => $liquidacion->id, 'cliente_id' => $clienteId, 'monto' => $monto,
                'metodo_pago' => MetodoPago::Efectivo->value, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $aplicable = min($monto, $this->cuentas->deudaCliente($clienteId));
            if ($aplicable > 0.004) {
                $this->cuentas->aplicarPago($clienteId, $aplicable, $fecha, MetodoPago::Efectivo, null, $cobranzaId);
            }
            $this->resumen['filas']++;
        }

        $this->liquidaciones->recalcular($liquidacion->fresh());
    }

    /** Precios de venta: [código de cliente, presentación, precio, vigente desde]. */
    public function precios(array $precios, string $fuente): void
    {
        foreach ($precios as [$codigo, $producto, $precio, $desde]) {
            $clienteId = Cliente::withTrashed()->where('codigo', $codigo)->value('id');
            $productoId = $this->productos[$producto] ?? null;
            if (! $clienteId || ! $productoId) {
                continue;
            }
            $vigente = PrecioVenta::where('cliente_id', $clienteId)->where('producto_id', $productoId)
                ->where('vigente_desde', '<=', $desde)->orderByDesc('vigente_desde')->orderByDesc('id')->value('precio');
            $mismoDia = PrecioVenta::where('cliente_id', $clienteId)->where('producto_id', $productoId)->whereDate('vigente_desde', $desde)->exists();
            if (($vigente !== null && abs((float) $vigente - $precio) < 0.005) || $mismoDia) {
                continue;
            }
            PrecioVenta::create([
                'cliente_id' => $clienteId, 'producto_id' => $productoId, 'precio' => round($precio, 2), 'vigente_desde' => $desde,
                'motivo' => "Variación de precio según el Excel ({$fuente}, hoja DATA)",
            ]);
            $this->resumen['precios']++;
        }
    }

    /**
     * Precio de compra de la empresa: [empresa, presentación, precio, vigente desde]. Se registra en las
     * instalaciones que estaban en el precio general de la empresa (no en las de precio especial),
     * sin validar hasta que se vea en la factura.
     */
    public function preciosCompra(array $precios, string $fuente): void
    {
        foreach ($precios as [$empresa, $producto, $precio, $desde]) {
            $empresaId = $this->empresas[$empresa] ?? null;
            $productoId = $this->productos[$producto] ?? null;
            if (! $empresaId || ! $productoId) {
                continue;
            }
            $vigente = fn (int $instalacion, string $fecha) => PrecioCompra::where('instalacion_id', $instalacion)->where('producto_id', $productoId)
                ->where('vigente_desde', '<=', $fecha)->orderByDesc('vigente_desde')->orderByDesc('id')->value('precio');
            $antes = Carbon::parse($desde)->subDay()->toDateString();
            $instalaciones = Instalacion::where('empresa_id', $empresaId)->pluck('id');
            $previos = $instalaciones->mapWithKeys(fn ($id) => [$id => $vigente($id, $antes)])->filter(fn ($p) => $p !== null);
            $general = $previos->map(fn ($p) => number_format((float) $p, 2, '.', ''))->countBy()->sortDesc()->keys()->first();
            foreach ($previos as $id => $anterior) {
                $actual = $vigente($id, $desde);
                if (number_format((float) $anterior, 2, '.', '') !== $general || abs((float) $actual - $precio) < 0.005) {
                    continue;
                }
                PrecioCompra::create([
                    'empresa_id' => $empresaId, 'instalacion_id' => $id, 'producto_id' => $productoId, 'precio' => round($precio, 2),
                    'vigente_desde' => $desde, 'motivo' => "Variación del precio de compra según el Excel ({$fuente})", 'validado' => false,
                ]);
                $this->resumen['precios_compra']++;
            }
        }
    }

    /** FISE del día de la hoja RESUMEN GNRAL (importe por responsable, en vales de S/ 20 y S/ 43). */
    public function fise(string $dia, array $importes): void
    {
        foreach ($importes as $alias => $importe) {
            $chofer = $this->chofer($this->alias($alias));
            if (! $chofer) {
                continue;
            }
            // Ruta pendiente: el FISE va a su liquidación más reciente de los últimos días.
            $liquidacion = Liquidacion::where('chofer_id', $chofer->id)->where('historico', true)->where('fecha_venta', '<=', $dia)
                ->where('fecha_venta', '>=', Carbon::parse($dia)->subDays(15)->toDateString())->orderByDesc('fecha_venta')->first();
            if (! $liquidacion || $liquidacion->fises()->exists()) {
                continue;
            }
            [$de20, $de43] = DatosHistoricos::vales((int) $importe);
            foreach ([20 => $de20, 43 => $de43] as $valor => $cantidad) {
                if ($cantidad > 0) {
                    $liquidacion->fises()->create(['valor' => $valor, 'cantidad' => $cantidad, 'subtotal' => $valor * $cantidad]);
                }
            }
            $this->liquidaciones->recalcular($liquidacion);
            $this->resumen['fise']++;
        }
    }

    /** Depósitos: [fecha, responsable, banco, empresa (cuenta), quién depositó, monto]. */
    public function depositos(array $depositos): void
    {
        foreach ($depositos as [$fecha, $responsable, $banco, $empresa, $quien, $monto]) {
            if (Deposito::whereDate('fecha', $fecha)->where('monto', $monto)->where('depositante', $quien)->exists()) {
                continue;
            }
            $cuenta = CuentaBancaria::firstOrCreate(['banco' => $banco, 'alias' => $empresa], ['moneda' => 'PEN', 'activo' => true]);
            Deposito::create([
                'fecha' => $fecha, 'cuenta_bancaria_id' => $cuenta->id, 'empresa_id' => $this->empresas[$empresa] ?? null,
                'chofer_id' => $this->chofer($this->alias($responsable))?->id, 'depositante' => $quien, 'monto' => $monto,
                'observaciones' => 'Importado del Excel (RESUMEN GNRAL).',
            ]);
            $this->resumen['depositos']++;
        }
    }

    private function alias(?string $alias): string
    {
        $alias = mb_strtoupper(trim((string) $alias));

        return self::ALIAS[$alias] ?? $alias;
    }

    private function chofer(string $alias): ?Chofer
    {
        return $this->choferes[$alias] ??= Chofer::withTrashed()->where('alias', $alias)->first()
            ?? ($alias === 'OTROS' ? Chofer::create(['alias' => 'OTROS', 'tipo' => TipoChofer::Otro, 'activo' => true, 'observaciones' => 'Importado del Excel.']) : null);
    }

    /** Cliente por código; si no existe, por nombre; si tampoco, se crea (igual que la carga inicial). */
    private function clienteId(mixed $codigo, ?string $nombre, Chofer $chofer): int
    {
        if (is_int($codigo) && $nombre && ($id = Cliente::withTrashed()->where('codigo', $codigo)->value('id'))) {
            return $id;
        }
        $clave = fn (?string $t) => preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii((string) $t)));
        $buscado = $clave($nombre ?: (string) $codigo);
        $id = Cliente::withTrashed()->get(['id', 'nombre'])->first(fn ($c) => $clave($c->nombre) === $buscado)?->id;
        if ($id) {
            return $id;
        }

        return Cliente::create([
            'codigo' => max(1000, (int) Cliente::withTrashed()->max('codigo') + 1),
            'nombre' => mb_strtoupper($nombre ?: "CLIENTE {$codigo}"),
            'chofer_id' => $chofer->id, 'tipo' => $chofer->tipo === TipoChofer::Ruta ? 'ruta' : 'local', 'activo' => true,
            'observaciones' => 'Creado al importar ventas del Excel (no estaba en la hoja DATA).',
        ])->id;
    }
}
