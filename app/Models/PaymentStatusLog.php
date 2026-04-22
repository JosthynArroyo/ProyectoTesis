<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStatusLog extends Model
{
    use HasFactory;

    protected $table = 'payment_status_logs';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'pago_id',
        'estado_anterior',
        'estado_nuevo',
        'actor_id',
        'actor_rol',
        'motivo',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
