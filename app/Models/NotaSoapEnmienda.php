<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaSoapEnmienda extends Model
{
    use HasFactory;

    protected $table = 'nota_soap_enmiendas';

    protected $fillable = [
        'nota_soap_id',
        'user_id',
        'motivo',
        'contenido',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function nota()
    {
        return $this->belongsTo(NotaSoap::class, 'nota_soap_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
