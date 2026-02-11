<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomeSetting extends Model
{
    protected $table = 'landing_welcome_settings';

    protected $fillable = [
        'header_logo',
        'header_name',
        'header_show_socials',
        'hero_badge',
        'hero_title',
        'hero_subtitle',
        'hero_primary_text',
        'hero_secondary_text',
        'hero_show_primary',
        'hero_show_secondary',
        'prices_subtitle',
        'show_services_block',
    ];

    protected $casts = [
        'header_show_socials' => 'boolean',
        'hero_show_primary' => 'boolean',
        'hero_show_secondary' => 'boolean',
        'show_services_block' => 'boolean',
    ];
}
