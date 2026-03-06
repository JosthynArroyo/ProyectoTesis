<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceiptLog extends Model
{
    use HasFactory;

    protected $table = 'payment_receipt_logs';

    public $timestamps = true;
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_receipt_id',
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

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

