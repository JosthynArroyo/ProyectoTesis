<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomeStat extends Model
{
    protected $table = 'landing_welcome_stats';

    protected $fillable = [
        'label',
        'value',
        'note',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
