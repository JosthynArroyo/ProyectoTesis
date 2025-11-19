<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceProfile extends Model
{
    protected $fillable = [
        'user_id',
        'descriptor',
        'threshold',
        'failed_attempts',
        'last_verified_at',
    ];

    protected $casts = [
        'descriptor' => 'array',
        'last_verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
