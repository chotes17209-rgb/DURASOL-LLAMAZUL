<?php

use App\Services\CostoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rentabilidad: costo unitario en cada línea de venta, costo referencial por producto
 * y compras en planta de periodos sin parte diario (importadas del Excel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('costo_referencial', 10, 2)->nullable()->after('se_compra_en_planta');
        });
        Schema::table('liquidacion_items', function (Blueprint $table) {
            $table->decimal('costo_unitario', 10, 2)->nullable()->after('total');
        });
        Schema::create('compras_planta', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedInteger('cantidad');
            $table->string('origen', 30)->default('excel');
            $table->timestamps();
        });

        // Costos referenciales de lo que no se compra en planta (últimos del Excel de rentabilidad).
        foreach (['C10' => 30.90, 'C45' => 148.20, 'K10' => 66.00] as $codigo => $costo) {
            DB::table('productos')->where('codigo', $codigo)->update(['costo_referencial' => $costo]);
        }

        $this->completarDatosExistentes();
    }

    /** En una base ya cargada: costo de las ventas registradas y compras históricas de agosto. */
    private function completarDatosExistentes(): void
    {
        if (! DB::table('liquidacion_items')->exists()) {
            return;
        }
        $productos = DB::table('productos')->pluck('id', 'codigo');
        $empresas = DB::table('empresas')->pluck('id', 'nombre')->mapWithKeys(fn ($id, $n) => [mb_strtoupper($n) => $id]);

        // Ventas importadas: el costo del Excel por fecha, empresa y presentación (el más frecuente).
        $excel = [];
        $archivo = database_path('seeders/data/ventas.json');
        if (is_file($archivo)) {
            foreach (json_decode(file_get_contents($archivo), true) as $f) {
                $producto = $productos[$f[7] ?? ''] ?? null;
                $empresa = $empresas[mb_strtoupper((string) ($f[2] ?? ''))] ?? null;
                if ($producto && $empresa && ! empty($f[14])) {
                    $clave = "{$f[0]}|{$empresa}|{$producto}";
                    $excel[$clave][(string) $f[14]] = ($excel[$clave][(string) $f[14]] ?? 0) + 1;
                }
            }
        }

        $costos = app(CostoService::class);
        DB::table('liquidacion_items')->join('liquidaciones', 'liquidaciones.id', '=', 'liquidacion_items.liquidacion_id')
            ->whereNull('liquidacion_items.costo_unitario')
            ->select('liquidacion_items.id', 'liquidacion_items.empresa_id', 'liquidacion_items.producto_id', 'liquidaciones.fecha_venta', 'liquidaciones.historico')
            ->chunkById(1000, function ($items) use ($excel, $costos) {
                foreach ($items as $i) {
                    $fecha = substr((string) $i->fecha_venta, 0, 10);
                    $clave = "{$fecha}|{$i->empresa_id}|{$i->producto_id}";
                    if ($i->historico && isset($excel[$clave])) {
                        arsort($excel[$clave]);
                        $costo = (float) array_key_first($excel[$clave]);
                    } else {
                        $costo = $costos->costoUnitario($i->empresa_id, $i->producto_id, $fecha);
                    }
                    if ($costo !== null) {
                        DB::table('liquidacion_items')->where('id', $i->id)->update(['costo_unitario' => $costo]);
                    }
                }
            }, 'liquidacion_items.id', 'id');

        $compras = database_path('seeders/data/compras_agosto.json');
        if (is_file($compras) && ! DB::table('compras_planta')->exists() && DB::table('liquidaciones')->where('historico', true)->exists()) {
            $filas = [];
            foreach (json_decode(file_get_contents($compras), true) as [$fecha, $porEmpresa]) {
                foreach ($porEmpresa as $empresa => $cantidades) {
                    foreach ($cantidades as $codigo => $cantidad) {
                        if ($cantidad > 0 && isset($empresas[$empresa], $productos[$codigo])) {
                            $filas[] = ['fecha' => $fecha, 'empresa_id' => $empresas[$empresa], 'producto_id' => $productos[$codigo],
                                'cantidad' => (int) $cantidad, 'origen' => 'excel', 'created_at' => now(), 'updated_at' => now()];
                        }
                    }
                }
            }
            DB::table('compras_planta')->insert($filas);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_planta');
        Schema::table('liquidacion_items', fn (Blueprint $table) => $table->dropColumn('costo_unitario'));
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('costo_referencial'));
    }
};
