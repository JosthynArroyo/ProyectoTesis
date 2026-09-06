<?php

namespace Database\Seeders;

use App\Models\FeatureAccessRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoPersonalizacionSolicitudesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@demo-clinigest.test')->first();
        $superadmin = User::where('email', 'superadmin@demo-clinigest.test')->first();

        if (! $admin || ! $superadmin) {
            return;
        }

        // 1. Solicitud pendiente (permite al Superadmin interactuar con Aprobar y Rechazar)
        FeatureAccessRequest::updateOrCreate(
            [
                'user_id' => $admin->id,
                'feature' => 'personalizacion',
                'status' => 'pending',
            ],
            [
                'approved_until' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'revoked_at' => null,
                'revoked_by' => null,
                'notes' => 'Solicitud para actualizar los datos de contacto institucional y textos de bienvenida para la campaña de vacunación.',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ]
        );

        // 2. Solicitud aprobada y vigente (permite al Superadmin ver el historial activo y la opción de Revocar)
        FeatureAccessRequest::updateOrCreate(
            [
                'user_id' => $admin->id,
                'feature' => 'personalizacion',
                'status' => 'approved',
            ],
            [
                'approved_until' => now()->addDays(15),
                'reviewed_at' => now()->subDays(3),
                'reviewed_by' => $superadmin->id,
                'revoked_at' => null,
                'revoked_by' => null,
                'notes' => 'Aprobado para actualización trimestral de servicios médicos y cuerpo facultativo.',
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(3),
            ]
        );
    }
}
