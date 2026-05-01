<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LandingWelcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hexColorRule = ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'];

        return [
            'branding_name' => ['nullable', 'string', 'max:120'],
            'branding_navbar_text' => ['nullable', 'string', 'max:120'],
            'branding_institutional_name' => ['nullable', 'string', 'max:120'],
            'branding_institutional_badge' => ['nullable', 'string', 'max:160'],
            'branding_accent' => $hexColorRule,
            'branding_accent_strong' => $hexColorRule,
            'branding_accent_soft' => $hexColorRule,
            'header_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'header_logo_path' => ['nullable', 'string', 'max:180'],
            'branding_favicon' => ['nullable', 'image', 'mimes:png,webp,svg', 'max:2048'],
            'branding_favicon_path' => ['nullable', 'string', 'max:180'],

            'header_show_home' => ['nullable', 'boolean'],
            'header_show_services' => ['nullable', 'boolean'],
            'header_show_contact' => ['nullable', 'boolean'],
            'header_sticky_enabled' => ['nullable', 'boolean'],
            'header_login_text' => ['nullable', 'string', 'max:25'],
            'header_navigation_order' => ['nullable', 'array'],
            'header_navigation_order.*' => ['nullable', Rule::in(['home', 'services', 'contact'])],

            'hero_title' => ['nullable', 'string', 'max:180'],
            'hero_subtitle' => ['nullable', 'string', 'max:240'],
            'hero_primary_text' => ['nullable', 'string', 'max:25'],
            'hero_secondary_text' => ['nullable', 'string', 'max:25'],
            'hero_show_primary' => ['nullable', 'boolean'],
            'hero_show_secondary' => ['nullable', 'boolean'],
            'intro_badge' => ['nullable', 'string', 'max:60'],
            'intro_feature_1_title' => ['nullable', 'string', 'max:120'],
            'intro_feature_1_text' => ['nullable', 'string', 'max:240'],
            'intro_feature_4_title' => ['nullable', 'string', 'max:120'],
            'intro_feature_4_text' => ['nullable', 'string', 'max:240'],
            'services_badge' => ['nullable', 'string', 'max:60'],
            'services_title' => ['nullable', 'string', 'max:160'],
            'services_subtitle' => ['nullable', 'string', 'max:240'],
            'services_button_text' => ['nullable', 'string', 'max:25'],
            'prices_badge' => ['nullable', 'string', 'max:60'],
            'prices_title' => ['nullable', 'string', 'max:160'],
            'prices_subtitle' => ['nullable', 'string', 'max:240'],
            'prices_highlight_title' => ['nullable', 'string', 'max:120'],
            'prices_highlight_subtitle' => ['nullable', 'string', 'max:240'],
            'prices_visit_title' => ['nullable', 'string', 'max:120'],
            'prices_visit_subtitle' => ['nullable', 'string', 'max:240'],
            'doctors_badge' => ['nullable', 'string', 'max:60'],
            'doctors_title' => ['nullable', 'string', 'max:160'],
            'doctors_subtitle' => ['nullable', 'string', 'max:240'],
            'doctors_pill' => ['nullable', 'string', 'max:60'],
            'show_services_block' => ['nullable', 'boolean'],

            'slides' => ['nullable', 'array'],
            'slides.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'slides.*.image_path' => ['nullable', 'string', 'max:180'],
            'slides.*.alt' => ['nullable', 'string', 'max:120'],
            'slides.*.title' => ['nullable', 'string', 'max:120'],
            'slides.*.subtitle' => ['nullable', 'string', 'max:120'],
            'slides.*.text' => ['nullable', 'string', 'max:240'],
            'slides.*.is_active' => ['nullable', 'boolean'],
            'slides.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'doctors' => ['nullable', 'array'],
            'doctors.*.name' => ['nullable', 'string', 'max:80'],
            'doctors.*.specialty' => ['nullable', 'string', 'max:80'],
            'doctors.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'doctors.*.photo_path' => ['nullable', 'string', 'max:180'],
            'doctors.*.experience_label' => ['nullable', 'string', 'max:120'],
            'doctors.*.featured_label' => ['nullable', 'string', 'max:60'],
            'doctors.*.attendance_label' => ['nullable', 'string', 'max:80'],
            'doctors.*.availability_label' => ['nullable', 'string', 'max:80'],
            'doctors.*.cta_text' => ['nullable', 'string', 'max:25'],
            'doctors.*.pill_text' => ['nullable', 'string', 'max:60'],
            'doctors.*.is_active' => ['nullable', 'boolean'],
            'doctors.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'prices' => ['nullable', 'array'],
            'prices.*.service' => ['nullable', 'string', 'max:80'],
            'prices.*.price' => ['nullable', 'string', 'max:30'],
            'prices.*.is_active' => ['nullable', 'boolean'],
            'prices.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'featured_specialties' => ['nullable', 'array'],
            'featured_specialties.*' => [
                'nullable',
                'integer',
                Rule::exists('especialidades', 'id')->where('activo', true),
            ],
            'featured_specialty_descriptions' => ['nullable', 'array'],
            'featured_specialty_descriptions.*' => ['nullable', 'string', 'max:240'],

            'footer_legal_text' => ['nullable', 'string', 'max:255'],
            'footer_institutional_text' => ['nullable', 'string', 'max:255'],
            'footer_show_home_link' => ['nullable', 'boolean'],
            'footer_show_services_link' => ['nullable', 'boolean'],
            'footer_show_contact_link' => ['nullable', 'boolean'],
            'footer_show_assistant_link' => ['nullable', 'boolean'],
            'footer_show_privacy_link' => ['nullable', 'boolean'],
            'footer_show_terms_link' => ['nullable', 'boolean'],
            'footer_home_label' => ['nullable', 'string', 'max:40'],
            'footer_services_label' => ['nullable', 'string', 'max:40'],
            'footer_contact_label' => ['nullable', 'string', 'max:40'],
            'footer_assistant_label' => ['nullable', 'string', 'max:60'],
            'footer_privacy_label' => ['nullable', 'string', 'max:80'],
            'footer_terms_label' => ['nullable', 'string', 'max:80'],
            'footer_contact_address' => ['nullable', 'string', 'max:255'],
            'footer_contact_phone' => ['nullable', 'string', 'max:40'],
            'footer_contact_email' => ['nullable', 'email:rfc', 'max:120'],

            'legal_privacy_title' => ['nullable', 'string', 'max:160'],
            'legal_privacy_updated_at' => ['nullable', 'string', 'max:80'],
            'legal_privacy_body' => ['nullable', 'string'],
            'legal_terms_title' => ['nullable', 'string', 'max:160'],
            'legal_terms_updated_at' => ['nullable', 'string', 'max:80'],
            'legal_terms_body' => ['nullable', 'string'],

            'visual_soft_primary' => $hexColorRule,
            'visual_soft_secondary' => $hexColorRule,
            'visual_gradient_start' => $hexColorRule,
            'visual_gradient_end' => $hexColorRule,
            'visual_badge_soft' => $hexColorRule,
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $show = $this->boolean('show_services_block');
            $ids = array_values(array_filter($this->input('featured_specialties', [])));

            if ($show && count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('featured_specialties', 'Las especialidades seleccionadas no pueden repetirse.');
            }

            $navigationOrder = array_values(array_filter($this->input('header_navigation_order', [])));
            if (! empty($navigationOrder) && count($navigationOrder) !== count(array_unique($navigationOrder))) {
                $validator->errors()->add('header_navigation_order', 'El orden de navegación no puede repetir elementos.');
            }

            $activeDoctors = collect($this->input('doctors', []))
                ->filter(function ($doctor, $index) {
                    if (! is_array($doctor) || empty($doctor['is_active'])) {
                        return false;
                    }

                    $hasVisibleData = filled($doctor['name'] ?? null)
                        || filled($doctor['specialty'] ?? null)
                        || filled($doctor['photo_path'] ?? null)
                        || filled($doctor['experience_label'] ?? null)
                        || filled($doctor['featured_label'] ?? null)
                        || filled($doctor['attendance_label'] ?? null)
                        || filled($doctor['availability_label'] ?? null)
                        || filled($doctor['cta_text'] ?? null)
                        || filled($doctor['pill_text'] ?? null);

                    return $hasVisibleData || $this->hasFile("doctors.$index.photo");
                })
                ->count();

            if ($activeDoctors > 3) {
                $validator->errors()->add(
                    'doctors',
                    'Los máximos de doctores destacados son 3. Debes desmarcar el cuadro de "Mostrar doctor" y marcar el cuadro del nuevo doctor destacado.'
                );
            }
        });
    }
}
