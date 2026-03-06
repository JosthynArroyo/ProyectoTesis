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
        return [
            'hero_badge' => ['required', 'string', 'max:120'],
            'hero_title' => ['required', 'string', 'max:180'],
            'hero_subtitle' => ['required', 'string', 'max:240'],
            'hero_followup_title' => ['required', 'string', 'max:120'],
            'hero_followup_subtitle' => ['required', 'string', 'max:240'],
            'hero_primary_text' => ['required', 'string', 'max:60'],
            'hero_secondary_text' => ['required', 'string', 'max:60'],
            'hero_show_primary' => ['required', 'boolean'],
            'hero_show_secondary' => ['required', 'boolean'],
            'intro_badge' => ['required', 'string', 'max:60'],
            'intro_title' => ['required', 'string', 'max:180'],
            'intro_subtitle' => ['required', 'string', 'max:240'],
            'intro_feature_1_title' => ['required', 'string', 'max:120'],
            'intro_feature_1_text' => ['required', 'string', 'max:240'],
            'intro_feature_2_title' => ['required', 'string', 'max:120'],
            'intro_feature_2_text' => ['required', 'string', 'max:240'],
            'intro_feature_3_title' => ['required', 'string', 'max:120'],
            'intro_feature_3_text' => ['required', 'string', 'max:240'],
            'intro_feature_4_title' => ['required', 'string', 'max:120'],
            'intro_feature_4_text' => ['required', 'string', 'max:240'],
            'services_badge' => ['required', 'string', 'max:60'],
            'services_title' => ['required', 'string', 'max:160'],
            'services_subtitle' => ['required', 'string', 'max:240'],
            'services_button_text' => ['required', 'string', 'max:60'],
            'prices_badge' => ['required', 'string', 'max:60'],
            'prices_title' => ['required', 'string', 'max:160'],
            'prices_subtitle' => ['required', 'string', 'max:240'],
            'prices_button_text' => ['required', 'string', 'max:60'],
            'prices_highlight_title' => ['required', 'string', 'max:120'],
            'prices_highlight_subtitle' => ['required', 'string', 'max:240'],
            'prices_highlight_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'prices_highlight_image_path' => ['nullable', 'string', 'max:180'],
            'prices_visit_title' => ['required', 'string', 'max:120'],
            'prices_visit_subtitle' => ['required', 'string', 'max:240'],
            'doctors_badge' => ['required', 'string', 'max:60'],
            'doctors_title' => ['required', 'string', 'max:160'],
            'doctors_subtitle' => ['required', 'string', 'max:240'],
            'doctors_pill' => ['required', 'string', 'max:100'],
            'show_services_block' => ['required', 'boolean'],

            'stats' => ['required', 'array'],
            'stats.*.label' => ['required', 'string', 'max:60'],
            'stats.*.value' => ['required', 'string', 'max:30'],
            'stats.*.note' => ['required', 'string', 'max:80'],
            'stats.*.is_active' => ['required', 'boolean'],
            'stats.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],

            'slides' => ['required', 'array'],
            'slides.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'slides.*.image_path' => ['nullable', 'string', 'max:180'],
            'slides.*.is_active' => ['required', 'boolean'],
            'slides.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],

            'cards' => ['required', 'array'],
            'cards.*.title' => ['required', 'string', 'max:80'],
            'cards.*.value' => ['required', 'string', 'max:40'],
            'cards.*.description' => ['required', 'string', 'max:160'],
            'cards.*.icon' => ['required', 'string', 'max:60'],
            'cards.*.is_active' => ['required', 'boolean'],
            'cards.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],

            'doctors' => ['required', 'array'],
            'doctors.*.name' => ['required', 'string', 'max:80'],
            'doctors.*.specialty' => ['required', 'string', 'max:80'],
            'doctors.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'doctors.*.photo_path' => ['nullable', 'string', 'max:180'],
            'doctors.*.is_active' => ['required', 'boolean'],
            'doctors.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],

            'prices' => ['required', 'array'],
            'prices.*.service' => ['required', 'string', 'max:80'],
            'prices.*.price' => ['required', 'string', 'max:30'],
            'prices.*.is_active' => ['required', 'boolean'],
            'prices.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],

            'featured_specialties' => ['required', 'array'],
            'featured_specialties.*' => [
                'nullable',
                'integer',
                Rule::exists('especialidades', 'id')->where('activo', true),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $show = $this->boolean('show_services_block');
            $ids = array_values(array_filter($this->input('featured_specialties', [])));

            if ($show && count($ids) !== 3) {
                $validator->errors()->add('featured_specialties', 'Debes seleccionar exactamente 3 especialidades activas.');
                return;
            }

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('featured_specialties', 'Las especialidades seleccionadas no pueden repetirse.');
            }

        });
    }
}
