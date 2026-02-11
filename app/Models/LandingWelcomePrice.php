<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomePrice extends Model
{
    protected $table = 'landing_welcome_prices';

    protected $fillable = [
        'service',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
