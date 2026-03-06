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
            'contact_info_badge' => ['required', 'string', 'max:60'],
            'contact_title' => ['required', 'string', 'max:120'],
            'contact_subtitle' => ['required', 'string', 'max:240'],
            'contact_address_label' => ['required', 'string', 'max:40'],
            'contact_address' => ['required', 'string', 'max:180'],
            'contact_phone_label' => ['required', 'string', 'max:40'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'contact_hours_label' => ['required', 'string', 'max:40'],
            'contact_hours' => ['required', 'string', 'max:120'],
            'contact_map_title' => ['required', 'string', 'max:120'],
            'contact_map_embed' => ['required', 'string', 'max:4000', 'regex:/^(https:\/\/|\/maps\/)/i'],

            'contact_form_section_badge' => ['required', 'string', 'max:60'],
            'contact_form_title' => ['required', 'string', 'max:120'],
            'contact_form_badge' => ['required', 'string', 'max:120'],
            'contact_form_submit_text' => ['required', 'string', 'max:60'],

            'contact_form_name_label' => ['required', 'string', 'max:60'],
            'contact_form_email_label' => ['required', 'string', 'max:60'],
            'contact_form_phone_label' => ['required', 'string', 'max:60'],
            'contact_form_subject_label' => ['required', 'string', 'max:60'],
            'contact_form_subject_placeholder' => ['required', 'string', 'max:120'],
            'contact_form_message_label' => ['required', 'string', 'max:60'],
            'contact_form_message_placeholder' => ['required', 'string', 'max:180'],
            'contact_form_message_help' => ['required', 'string', 'max:180'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_map_embed.regex' => 'El mapa debe iniciar con https:// o /maps/.',
        ];
    }
}
