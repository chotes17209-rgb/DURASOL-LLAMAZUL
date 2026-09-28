<?php

namespace App\Models;

use App\Enums\TipoDocumentoVehicular;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehiculo extends Model
{
    use Auditable, SoftDeletes;

    public const TIPOS = ['camion' => 'Camión', 'furgon' => 'Furgón', 'camioneta' => 'Camioneta', 'mototaxi' => 'Mototaxi', 'otro' => 'Otro'];

    public const ESTADOS = ['operativo' => 'Operativo', 'mantenimiento' => 'En mantenimiento', 'inactivo' => 'Inactivo'];

    /** Documentos que todo vehículo debe tener vigentes. */
    public const DOCUMENTOS_OBLIGATORIOS = [TipoDocumentoVehicular::Soat, TipoDocumentoVehicular::RevisionTecnica, TipoDocumentoVehicular::Dgh];

    protected $table = 'vehiculos';

    protected $fillable = [
        'placa', 'tipo', 'marca', 'modelo', 'anio', 'color', 'capacidad_balones',
        'kilometraje', 'empresa_id', 'estado', 'observaciones',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function choferes(): HasMany
    {
        return $this->hasMany(Chofer::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(VehiculoDocumento::class)->orderByDesc('fecha_vencimiento');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(VehiculoMantenimiento::class)->orderByDesc('fecha');
    }

    public function scopeOperativos(Builder $query): Builder
    {
        return $query->where('estado', '!=', 'inactivo')->orderBy('placa');
    }

    /** Documento más reciente de un tipo (el que define si está vigente). */
    public function documentoVigente(TipoDocumentoVehicular $tipo): ?VehiculoDocumento
    {
        return $this->documentos
            ->where('tipo', $tipo)
            ->sortByDesc(fn ($d) => $d->fecha_vencimiento?->timestamp ?? 0)
            ->first();
    }

    /**
     * Semáforo de documentos obligatorios.
     *
     * @return array<string, array{label: string, documento: ?VehiculoDocumento, estado: string}>
     */
    public function estadoDocumentos(): array
    {
        $resultado = [];
        foreach (self::DOCUMENTOS_OBLIGATORIOS as $tipo) {
            $doc = $this->documentoVigente($tipo);
            $resultado[$tipo->value] = [
                'label' => $tipo->label(),
                'documento' => $doc,
                'estado' => $doc?->estadoVencimiento() ?? 'sin_registro',
            ];
        }

        return $resultado;
    }
}
