<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomeInfoCard extends Model
{
    protected $table = 'landing_welcome_info_cards';

    protected $fillable = [
        'title',
        'value',
        'description',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
