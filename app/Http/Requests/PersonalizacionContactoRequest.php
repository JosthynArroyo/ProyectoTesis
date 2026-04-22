<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonalizacionContactoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_info_badge' => ['nullable', 'string', 'max:60'],
            'contact_title' => ['nullable', 'string', 'max:120'],
            'contact_subtitle' => ['nullable', 'string', 'max:240'],
            'contact_address_label' => ['nullable', 'string', 'max:40'],
            'contact_address' => ['nullable', 'string', 'max:180'],
            'contact_phone_label' => ['nullable', 'string', 'max:40'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_hours_label' => ['nullable', 'string', 'max:40'],
            'contact_hours' => ['nullable', 'string', 'max:120'],
            'contact_map_title' => ['nullable', 'string', 'max:120'],
            'contact_map_embed' => ['nullable', 'string', 'max:4000', 'regex:/^(https:\/\/|\/maps\/)/i'],

            'contact_form_section_badge' => ['nullable', 'string', 'max:60'],
            'contact_form_title' => ['nullable', 'string', 'max:120'],
            'contact_form_badge' => ['nullable', 'string', 'max:120'],
            'contact_form_submit_text' => ['nullable', 'string', 'max:60'],

            'contact_form_name_label' => ['nullable', 'string', 'max:60'],
            'contact_form_email_label' => ['nullable', 'string', 'max:60'],
            'contact_form_phone_label' => ['nullable', 'string', 'max:60'],
            'contact_form_subject_label' => ['nullable', 'string', 'max:60'],
            'contact_form_subject_placeholder' => ['nullable', 'string', 'max:120'],
            'contact_form_message_label' => ['nullable', 'string', 'max:60'],
            'contact_form_message_placeholder' => ['nullable', 'string', 'max:180'],
            'contact_form_message_help' => ['nullable', 'string', 'max:180'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_map_embed.regex' => 'El mapa debe iniciar con https:// o /maps/.',
        ];
    }
}
