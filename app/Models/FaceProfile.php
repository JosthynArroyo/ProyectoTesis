<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceProfile extends Model
{
    protected $fillable = [
        'user_id',
        'descriptor',
        'descriptors',
        'threshold',
        'failed_attempts',
        'last_verified_at',
        'last_enrolled_at',
        'last_enroll_ip',
        'last_enroll_user_agent',
    ];

    protected $casts = [
        'descriptor' => 'array',
        'descriptors' => 'array',
        'last_verified_at' => 'datetime',
        'last_enrolled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
