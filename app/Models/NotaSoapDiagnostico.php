<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaSoapDiagnostico extends Model
{
    use HasFactory;

    protected $table = 'nota_soap_diagnosticos';

    protected $fillable = [
        'nota_soap_id',
        'tipo',
        'texto',
        'cie10',
    ];

    public function nota()
    {
        return $this->belongsTo(NotaSoap::class, 'nota_soap_id');
    }
}
