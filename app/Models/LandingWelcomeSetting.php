<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomeSetting extends Model
{
    protected $table = 'landing_welcome_settings';

    protected $fillable = [
        'header_logo',
        'header_name',
        'header_login_text',
        'header_show_socials',
        'hero_title',
        'hero_subtitle',
        'hero_primary_text',
        'hero_secondary_text',
        'hero_show_primary',
        'hero_show_secondary',
        'intro_badge',
        'intro_feature_1_title',
        'intro_feature_1_text',
        'intro_feature_4_title',
        'intro_feature_4_text',
        'services_badge',
        'services_title',
        'services_subtitle',
        'services_button_text',
        'prices_badge',
        'prices_title',
        'prices_subtitle',
        'prices_highlight_title',
        'prices_highlight_subtitle',
        'prices_visit_title',
        'prices_visit_subtitle',
        'doctors_badge',
        'doctors_title',
        'doctors_subtitle',
        'doctors_pill',
        'show_services_block',
    ];

    protected $casts = [
        'header_show_socials' => 'boolean',
        'hero_show_primary' => 'boolean',
        'hero_show_secondary' => 'boolean',
        'show_services_block' => 'boolean',
    ];
}
