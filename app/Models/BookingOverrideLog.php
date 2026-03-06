<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingOverrideLog extends Model
{
    use HasFactory;

    protected $table = 'booking_override_logs';

    public $timestamps = true;
    public const UPDATED_AT = null;

    protected $fillable = [
        'paciente_id',
        'actor_id',
        'cita_id',
        'motivo',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }
}
