<?php

namespace App\Enums;

enum LabResultType: string
{
    case NUMERIC = 'numeric';
    case CODED = 'coded';
    case TEXT = 'text';
    case TITER = 'titer';
    case BLOOD_GROUP = 'blood_group';
    case MICROSCOPY = 'microscopy';
    case CULTURE = 'culture';
    case CALCULATED = 'calculated';
    case PANEL = 'panel';

    public function label(): string
    {
        return match ($this) {
            self::NUMERIC => 'Numérico',
            self::CODED => 'Codificado / Opción',
            self::TEXT => 'Texto libre',
            self::TITER => 'Título / Dilución',
            self::BLOOD_GROUP => 'Grupo Sanguíneo',
            self::MICROSCOPY => 'Microscopía',
            self::CULTURE => 'Cultivo / Microbiología',
            self::CALCULATED => 'Calculado',
            self::PANEL => 'Panel de Exámenes',
        };
    }
}
