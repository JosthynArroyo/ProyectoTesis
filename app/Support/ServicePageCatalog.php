<?php

namespace App\Support;

use App\Models\Especialidad;
use Illuminate\Support\Str;

class ServicePageCatalog
{
    public static function heroImagePath(): string
    {
        return 'images/servicios/hero-servicios.png';
    }

    public static function catalog(): array
    {
        return [
            'dermatologia' => [
                'tag' => 'especialidad',
                'tag_label' => 'Especialidad',
                'icon' => 'ri-user-heart-line',
                'badge' => 'Cita presencial',
                'badge2' => 'Segun disponibilidad',
                'image_path' => 'images/servicios/dermatologia.jpg',
                'image_class' => 'object-cover object-[center_22%]',
            ],
            'ginecologia' => [
                'tag' => 'especialidad',
                'tag_label' => 'Especialidad',
                'icon' => 'ri-women-line',
                'badge' => 'Cita presencial',
                'badge2' => 'Segun disponibilidad',
                'image_path' => 'images/servicios/ginecologia.jpg',
                'image_class' => 'object-cover object-[center_24%]',
            ],
            Especialidad::normalizedLaboratorioClinico() => [
                'tag' => 'diagnostico',
                'tag_label' => 'Diagnostico',
                'icon' => 'ri-test-tube-line',
                'badge' => 'Con solicitud medica',
                'badge2' => 'Resultados en el sistema',
                'image_path' => 'images/servicios/laboratorio.jpg',
                'image_class' => 'object-cover object-center',
            ],
            'medicina general' => [
                'tag' => 'general',
                'tag_label' => 'General',
                'icon' => 'ri-stethoscope-line',
                'badge' => 'Cita presencial',
                'badge2' => 'Segun disponibilidad',
                'image_path' => 'images/servicios/medicina-general.jpg',
                'image_class' => 'object-cover object-[center_18%]',
            ],
            'odontologia' => [
                'tag' => 'procedimiento',
                'tag_label' => 'Procedimiento',
                'icon' => 'ri-tooth-line',
                'badge' => 'Cita presencial',
                'badge2' => 'Segun disponibilidad',
                'image_path' => 'images/servicios/odontologia.jpg',
                'image_class' => 'object-cover object-[center_28%]',
            ],
            'pediatria' => [
                'tag' => 'especialidad',
                'tag_label' => 'Especialidad',
                'icon' => 'ri-bear-smile-line',
                'badge' => 'Atencion pediatrica',
                'badge2' => 'Segun disponibilidad',
                'image_path' => 'images/servicios/pediatria.jpg',
                'image_class' => 'object-cover object-[center_24%]',
            ],
        ];
    }

    public static function fallback(): array
    {
        return [
            'tag' => 'especialidad',
            'tag_label' => 'Especialidad',
            'icon' => 'ri-stethoscope-line',
            'badge' => 'Cita presencial',
            'badge2' => 'Segun disponibilidad',
            'image_path' => 'images/servicios/medicina-general.jpg',
            'image_class' => 'object-cover object-[center_18%]',
        ];
    }

    public static function normalizeName(?string $value): string
    {
        return (string) Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim();
    }
}
