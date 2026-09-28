<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de un parte diario. Según el bloque, las columnas significan:
 *  - llenos: s10, s45, m10 (balones con gas) y cambio_* (balones fallados);
 *  - vacíos: s10, s45 (plomos Solgas) y color_s10, color_s45 (de otras marcas).
 */
class ParteFila extends Model
{
    public const LLENO_INGRESO = 'lleno_ingreso';

    public const LLENO_SALIDA = 'lleno_salida';

    public const VACIO_INGRESO = 'vacio_ingreso';

    public const VACIO_SALIDA = 'vacio_salida';

    public const BLOQUES = [self::LLENO_INGRESO, self::LLENO_SALIDA, self::VACIO_INGRESO, self::VACIO_SALIDA];

    public const NOMBRES_BLOQUE = [
        self::LLENO_INGRESO => 'Ingreso de llenos', self::LLENO_SALIDA => 'Salida de llenos',
        self::VACIO_INGRESO => 'Ingreso de vacíos', self::VACIO_SALIDA => 'Salida de vacíos',
    ];

    /** Columnas de cantidades de cada tipo de bloque (clave => título). */
    public const COLUMNAS_LLENOS = ['s10' => 'S-10', 's45' => 'S-45', 'm10' => 'M-10', 'cambio_s10' => 'Camb. S-10', 'cambio_s45' => 'Camb. S-45', 'cambio_m10' => 'Camb. M-10'];

    public const COLUMNAS_VACIOS = ['s10' => 'Plomo S-10', 's45' => 'Plomo S-45', 'color_s10' => 'Color S-10', 'color_s45' => 'Color S-45'];

    /** Lugares frecuentes (el usuario puede escribir otros). */
    public const LUGARES = ['LOCAL', 'RUTA', 'PLANTA', 'MINA', 'CHILCA', 'CANJE', 'AJUSTE'];

    protected $table = 'parte_filas';

    protected $fillable = [
        'parte_id', 'bloque', 'orden', 'placa', 'vehiculo_id', 'responsable', 'chofer_id', 'lugar', 'empresa_id',
        'instalacion_id', 'numero_guia', 's10', 's45', 'm10', 'cambio_s10', 'cambio_s45', 'cambio_m10', 'color_s10', 'color_s45', 'observacion',
    ];

    public function parte(): BelongsTo
    {
        return $this->belongsTo(Parte::class);
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function instalacion(): BelongsTo
    {
        return $this->belongsTo(Instalacion::class);
    }

    public static function columnasDe(string $bloque): array
    {
        return str_starts_with($bloque, 'lleno') ? self::COLUMNAS_LLENOS : self::COLUMNAS_VACIOS;
    }

    public function esPlanta(): bool
    {
        return mb_strtoupper(trim((string) $this->lugar)) === 'PLANTA' || str_contains(mb_strtoupper((string) $this->lugar), 'PLANTA');
    }

    public function total(): int
    {
        return array_sum(array_map(fn ($c) => (int) $this->{$c}, array_keys(self::columnasDe($this->bloque))));
    }
}
