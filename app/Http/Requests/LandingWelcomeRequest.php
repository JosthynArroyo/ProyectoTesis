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
            'hero_badge' => ['nullable', 'string', 'max:120'],
            'hero_title' => ['nullable', 'string', 'max:180'],
            'hero_subtitle' => ['nullable', 'string', 'max:240'],
            'hero_followup_title' => ['nullable', 'string', 'max:120'],
            'hero_followup_subtitle' => ['nullable', 'string', 'max:240'],
            'hero_primary_text' => ['nullable', 'string', 'max:60'],
            'hero_secondary_text' => ['nullable', 'string', 'max:60'],
            'hero_show_primary' => ['nullable', 'boolean'],
            'hero_show_secondary' => ['nullable', 'boolean'],
            'intro_badge' => ['nullable', 'string', 'max:60'],
            'intro_title' => ['nullable', 'string', 'max:180'],
            'intro_subtitle' => ['nullable', 'string', 'max:240'],
            'intro_feature_1_title' => ['nullable', 'string', 'max:120'],
            'intro_feature_1_text' => ['nullable', 'string', 'max:240'],
            'intro_feature_2_title' => ['nullable', 'string', 'max:120'],
            'intro_feature_2_text' => ['nullable', 'string', 'max:240'],
            'intro_feature_3_title' => ['nullable', 'string', 'max:120'],
            'intro_feature_3_text' => ['nullable', 'string', 'max:240'],
            'intro_feature_4_title' => ['nullable', 'string', 'max:120'],
            'intro_feature_4_text' => ['nullable', 'string', 'max:240'],
            'services_badge' => ['nullable', 'string', 'max:60'],
            'services_title' => ['nullable', 'string', 'max:160'],
            'services_subtitle' => ['nullable', 'string', 'max:240'],
            'services_button_text' => ['nullable', 'string', 'max:60'],
            'prices_badge' => ['nullable', 'string', 'max:60'],
            'prices_title' => ['nullable', 'string', 'max:160'],
            'prices_subtitle' => ['nullable', 'string', 'max:240'],
            'prices_button_text' => ['nullable', 'string', 'max:60'],
            'prices_highlight_title' => ['nullable', 'string', 'max:120'],
            'prices_highlight_subtitle' => ['nullable', 'string', 'max:240'],
            'prices_highlight_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'prices_highlight_image_path' => ['nullable', 'string', 'max:180'],
            'prices_visit_title' => ['nullable', 'string', 'max:120'],
            'prices_visit_subtitle' => ['nullable', 'string', 'max:240'],
            'doctors_badge' => ['nullable', 'string', 'max:60'],
            'doctors_title' => ['nullable', 'string', 'max:160'],
            'doctors_subtitle' => ['nullable', 'string', 'max:240'],
            'doctors_pill' => ['nullable', 'string', 'max:100'],
            'show_services_block' => ['nullable', 'boolean'],

            'stats' => ['nullable', 'array'],
            'stats.*.label' => ['nullable', 'string', 'max:60'],
            'stats.*.value' => ['nullable', 'string', 'max:30'],
            'stats.*.note' => ['nullable', 'string', 'max:80'],
            'stats.*.is_active' => ['nullable', 'boolean'],
            'stats.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'slides' => ['nullable', 'array'],
            'slides.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'slides.*.image_path' => ['nullable', 'string', 'max:180'],
            'slides.*.is_active' => ['nullable', 'boolean'],
            'slides.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'cards' => ['nullable', 'array'],
            'cards.*.title' => ['nullable', 'string', 'max:80'],
            'cards.*.value' => ['nullable', 'string', 'max:40'],
            'cards.*.description' => ['nullable', 'string', 'max:160'],
            'cards.*.icon' => ['nullable', 'string', 'max:60'],
            'cards.*.is_active' => ['nullable', 'boolean'],
            'cards.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'doctors' => ['nullable', 'array'],
            'doctors.*.name' => ['nullable', 'string', 'max:80'],
            'doctors.*.specialty' => ['nullable', 'string', 'max:80'],
            'doctors.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'doctors.*.photo_path' => ['nullable', 'string', 'max:180'],
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
        });
    }
}
