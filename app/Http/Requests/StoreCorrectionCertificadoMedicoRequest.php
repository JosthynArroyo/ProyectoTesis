<?php

namespace App\Http\Requests;

class StoreCorrectionCertificadoMedicoRequest extends StoreCertificadoMedicoRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'motivo_correccion' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
    }
}
