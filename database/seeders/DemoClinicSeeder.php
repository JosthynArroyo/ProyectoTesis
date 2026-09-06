<?php

namespace Database\Seeders;

use App\Models\LandingWelcomeSetting;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class DemoClinicSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'branding.name', 'section' => 'branding', 'type' => 'string', 'value' => 'Clínica Josthyn Arroyo'],
            ['key' => 'branding.institutional_name', 'section' => 'branding', 'type' => 'string', 'value' => 'Clínica Josthyn Arroyo'],
            ['key' => 'branding.logo', 'section' => 'branding', 'type' => 'string', 'value' => 'images/demo/logo-demo.png'],
            ['key' => 'branding.favicon', 'section' => 'branding', 'type' => 'string', 'value' => 'images/demo/favicon-demo.png'],
            ['key' => 'branding.navbar_text', 'section' => 'branding', 'type' => 'string', 'value' => 'Atención Médica Integral & Diagnóstico'],
            ['key' => 'branding.primary_color', 'section' => 'branding', 'type' => 'string', 'value' => '#0284c7'],
            ['key' => 'contact.email', 'section' => 'contact', 'type' => 'string', 'value' => 'contacto@demo-clinigest.test'],
            ['key' => 'contact.phone', 'section' => 'contact', 'type' => 'string', 'value' => '+593 4 234 5678'],
            ['key' => 'contact.address', 'section' => 'contact', 'type' => 'string', 'value' => 'Av. de las Américas 1240 y Plaza Médica, Guayaquil'],
            ['key' => 'branding.footer_text', 'section' => 'branding', 'type' => 'string', 'value' => '© 2026 Clínica Josthyn Arroyo. Todos los derechos reservados.'],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'section' => $setting['section'],
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                ]
            );
        }

        LandingWelcomeSetting::updateOrCreate(
            ['id' => 1],
            [
                'header_name' => 'Clínica Josthyn Arroyo',
                'header_logo' => 'images/demo/logo-demo.png',
                'hero_title' => 'Cuidamos tu salud con excelencia y calidez',
                'hero_subtitle' => 'Especialidades médicas, laboratorio clínico automatizado y agendamiento en línea.',
                'hero_primary_text' => 'Agendar Cita',
                'hero_secondary_text' => 'Ver Especialidades',
                'hero_show_primary' => true,
                'hero_show_secondary' => true,
                'intro_badge' => 'Compromiso & Tecnología',
                'services_badge' => 'Servicios Integrales',
                'services_title' => 'Especialidades & Diagnóstico',
                'services_subtitle' => 'Contamos con profesionales altamente capacitados e infraestructura moderna.',
                'doctors_badge' => 'Especialistas',
                'doctors_title' => 'Conoce a Nuestro Cuerpo Médico',
                'doctors_subtitle' => 'Médicos certificados con amplia experiencia clínica.',
                'show_services_block' => true,
            ]
        );
    }
}
