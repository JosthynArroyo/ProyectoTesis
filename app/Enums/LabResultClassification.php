<?php

namespace App\Enums;

enum LabResultClassification: string
{
    case NORMAL = 'normal';
    case LOW = 'low';
    case HIGH = 'high';
    case CRITICAL_LOW = 'critical_low';
    case CRITICAL_HIGH = 'critical_high';
    case ABNORMAL = 'abnormal';
    case INDETERMINATE = 'indeterminate';
    case NOT_APPLICABLE = 'not_applicable';
    case NOT_EVALUATED = 'not_evaluated';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::LOW => 'Bajo',
            self::HIGH => 'Alto',
            self::CRITICAL_LOW => 'Crítico Bajo',
            self::CRITICAL_HIGH => 'Crítico Alto',
            self::ABNORMAL => 'Anormal',
            self::INDETERMINATE => 'Indeterminado',
            self::NOT_APPLICABLE => 'No aplica',
            self::NOT_EVALUATED => 'Sin evaluación (Pendiente)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NORMAL => 'normal',
            self::LOW => 'bajo',
            self::HIGH => 'alto',
            self::CRITICAL_LOW, self::CRITICAL_HIGH => 'critico',
            self::ABNORMAL => 'alto',
            self::INDETERMINATE, self::NOT_EVALUATED => 'info',
            self::NOT_APPLICABLE => 'normal',
        };
    }
}
