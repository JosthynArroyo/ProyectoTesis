<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SoapEnmiendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required','string','max:2000'],
            'contenido' => ['required','string','max:8000'],
        ];
    }
}
