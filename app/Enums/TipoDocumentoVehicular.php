<?php

namespace App\Enums;

/**
 * Documentos que se controlan por vehículo.
 */
enum TipoDocumentoVehicular: string
{
    use EnumHelpers;

    case Soat = 'soat';
    case RevisionTecnica = 'revision_tecnica';
    case Dgh = 'dgh';
    case TarjetaPropiedad = 'tarjeta_propiedad';
    case Poliza = 'poliza';
    case Matpel = 'matpel';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Soat => 'SOAT',
            self::RevisionTecnica => 'Revisión técnica',
            self::Dgh => 'Registro DGH / OSINERGMIN',
            self::TarjetaPropiedad => 'Tarjeta de propiedad',
            self::Poliza => 'Póliza de seguro',
            self::Matpel => 'Certificado MATPEL',
            self::Otro => 'Otro',
        };
    }
}
