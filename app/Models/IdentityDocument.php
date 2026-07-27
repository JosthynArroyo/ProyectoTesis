<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IdentityDocument extends Model
{
    protected $table = 'identity_documents';

    protected $fillable = [
        'pais',
        'tipo_documento',
        'numero_documento',
        'documentable_type',
        'documentable_id',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function normalize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/[^0-9]/', '', trim((string) $value));
    }
}
