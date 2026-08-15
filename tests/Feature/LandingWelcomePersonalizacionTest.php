<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeFeaturedSpecialty;
use App\Models\LandingWelcomeSlide;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LandingWelcomePersonalizacionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_welcome_form_hides_obsolete_fields(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $this->actingAs($superadmin)
            ->get(route('superadmin.personalizacion.bienvenida.edit'))
            ->assertOk()
            ->assertDontSee('Logo footer')
            ->assertDontSee('Imagen lateral')
            ->assertDontSee('Texto interno titulo')
            ->assertDontSee('Texto interno descriptivo')
            ->assertSee('Descripción breve visible');
    }

    public function test_superadmin_can_update_featured_specialty_descriptions_from_welcome(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');
        $especialidadUno = Especialidad::query()->firstOrCreate(
            ['nombre' => 'Dermatología'],
            ['descripcion' => 'Descripción original 1', 'activo' => true, 'orden' => 1]
        );
        $especialidadDos = Especialidad::query()->firstOrCreate(
            ['nombre' => 'Pediatría'],
            ['descripcion' => 'Descripción original 2', 'activo' => true, 'orden' => 2]
        );

        $response = $this->actingAs($superadmin)->put(route('superadmin.personalizacion.bienvenida.update'), [
            'show_services_block' => '1',
            'featured_specialties' => [
                $especialidadUno->id,
                $especialidadDos->id,
                '',
            ],
            'featured_specialty_descriptions' => [
                'Atención integral para piel, cabello y uñas.',
                'Controles y consultas pediátricas para cada etapa.',
                '',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Personalización guardada correctamente.');

        $especialidadUno->refresh();
        $especialidadDos->refresh();

        $this->assertSame('Atención integral para piel, cabello y uñas.', $especialidadUno->descripcion);
        $this->assertSame('Controles y consultas pediátricas para cada etapa.', $especialidadDos->descripcion);
        $this->assertSame(
            [$especialidadUno->id, $especialidadDos->id],
            LandingWelcomeFeaturedSpecialty::query()->orderBy('sort_order')->pluck('especialidad_id')->all(),
        );
    }

    public function test_superadmin_welcome_rejects_duplicate_navigation_items(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $response = $this->from(route('superadmin.personalizacion.bienvenida.edit'))
            ->actingAs($superadmin)
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'active_tab' => 'header',
                'header_navigation_order' => ['home', 'home', 'contact'],
            ]);

        $response->assertRedirect(route('superadmin.personalizacion.bienvenida.edit'));
        $response->assertSessionHasErrors('header_navigation_order');
    }

    public function test_superadmin_can_update_slide_copy_from_welcome(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $response = $this->actingAs($superadmin)->put(route('superadmin.personalizacion.bienvenida.update'), [
            'slides' => [
                [
                    'image_path' => 'img/hero1.jpg',
                    'alt' => 'Hero principal',
                    'title' => 'Seguimiento clínico',
                    'subtitle' => 'Panel actualizado',
                    'text' => 'Consulta el estado de tus atenciones y documentos desde un solo lugar.',
                    'sort_order' => 1,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Personalización guardada correctamente.');

        $slide = LandingWelcomeSlide::query()->firstOrFail();

        $this->assertSame('Seguimiento clínico', $slide->title);
        $this->assertSame('Panel actualizado', $slide->subtitle);
        $this->assertSame(
            'Consulta el estado de tus atenciones y documentos desde un solo lugar.',
            $slide->text,
        );
    }

    public function test_superadmin_welcome_rejects_more_than_three_visible_doctors(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $response = $this->from(route('superadmin.personalizacion.bienvenida.edit'))
            ->actingAs($superadmin)
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'active_tab' => 'equipo',
                'doctors' => [
                    ['name' => 'Doctor 1', 'specialty' => 'Medicina General', 'photo_path' => 'img/doctor1.jpg', 'is_active' => '1'],
                    ['name' => 'Doctor 2', 'specialty' => 'Pediatría', 'photo_path' => 'img/doctor2.jpg', 'is_active' => '1'],
                    ['name' => 'Doctor 3', 'specialty' => 'Dermatología', 'photo_path' => 'img/doctora1.jpg', 'is_active' => '1'],
                    ['name' => 'Doctor 4', 'specialty' => 'Ginecología', 'photo_path' => 'img/doctor1.jpg', 'is_active' => '1'],
                ],
            ]);

        $response->assertRedirect(route('superadmin.personalizacion.bienvenida.edit'));
        $response->assertSessionHasErrors('doctors');
    }

    public function test_home_shows_only_three_active_doctors_from_welcome(): void
    {
        foreach (range(1, 4) as $index) {
            LandingWelcomeDoctor::query()->create([
                'name' => 'Doctor '.$index,
                'specialty' => 'Especialidad '.$index,
                'photo_path' => 'img/doctor1.jpg',
                'experience_label' => '5+ años',
                'featured_label' => 'Destacado',
                'attendance_label' => 'Atención presencial',
                'availability_label' => 'Agenda disponible',
                'cta_text' => 'Agendar cita',
                'pill_text' => 'Atención segura',
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText('Doctor 1');
        $response->assertSeeText('Doctor 2');
        $response->assertSeeText('Doctor 3');
        $response->assertDontSeeText('Doctor 4');
    }

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user;
    }
}
