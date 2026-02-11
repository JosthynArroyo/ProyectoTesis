<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'cita_id',
        'user_id',
        'rol_receptor',
        'evento',
        'telefono',
        'mensaje',
        'estado',
        'provider',
        'provider_message_id',
        'error',
        'payload',
        'enviado_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'enviado_at' => 'datetime',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
