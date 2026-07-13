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
            'contact_email' => ['nullable', 'string', 'max:120'],
            'contact_hours_label' => ['nullable', 'string', 'max:40'],
            'contact_hours' => ['nullable', 'string', 'max:120'],
            'contact_map_title' => ['nullable', 'string', 'max:120'],
            'contact_map_embed' => ['nullable', 'string', 'max:4000', 'regex:/^(https:\/\/|\/maps\/)/i'],

            'contact_form_section_badge' => ['nullable', 'string', 'max:60'],
            'contact_form_title' => ['nullable', 'string', 'max:120'],
            'contact_form_badge' => ['nullable', 'string', 'max:120'],
            'contact_form_submit_text' => ['nullable', 'string', 'max:60'],

            'contact_form_name_label' => ['nullable', 'string', 'max:60'],
            'contact_form_name_placeholder' => ['nullable', 'string', 'max:120'],
            'contact_form_email_label' => ['nullable', 'string', 'max:60'],
            'contact_form_email_placeholder' => ['nullable', 'string', 'max:120'],
            'contact_form_phone_label' => ['nullable', 'string', 'max:60'],
            'contact_form_phone_placeholder' => ['nullable', 'string', 'max:120'],
            'contact_form_subject_label' => ['nullable', 'string', 'max:60'],
            'contact_form_subject_placeholder' => ['nullable', 'string', 'max:120'],
            'contact_form_message_label' => ['nullable', 'string', 'max:60'],
            'contact_form_message_placeholder' => ['nullable', 'string', 'max:180'],
            'contact_form_message_help' => ['nullable', 'string', 'max:180'],

            'clinic_hours' => [
                'sometimes',
                'array',
                'size:7',
                function ($attribute, $value, $fail) {
                    $keys = array_keys($value);
                    sort($keys);
                    if ($keys !== [1, 2, 3, 4, 5, 6, 7]) {
                        $fail('Los días configurados deben ser exactamente del 1 al 7.');
                    }
                }
            ],
            'clinic_hours.*.status' => ['required', 'in:0,1'],
            'clinic_hours.*.opening' => [
                'nullable',
                'required_if:clinic_hours.*.status,1',
                'date_format:H:i'
            ],
            'clinic_hours.*.closing' => [
                'nullable',
                'required_if:clinic_hours.*.status,1',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    preg_match('/clinic_hours\.(\d+)\.closing/', $attribute, $matches);
                    if (!empty($matches)) {
                        $day = $matches[1];
                        $status = request()->input("clinic_hours.{$day}.status");
                        if ($status == '1') {
                            $opening = request()->input("clinic_hours.{$day}.opening");
                            if ($opening && $value <= $opening) {
                                $fail('La hora de cierre debe ser posterior a la hora de apertura.');
                            }
                        }
                    }
                }
            ],
            'confirmar_conflictos' => ['nullable', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_map_embed.regex' => 'El mapa debe iniciar con https:// o /maps/.',
        ];
    }
}
