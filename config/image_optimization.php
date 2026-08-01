<?php

return [
    'disk' => env('IMAGE_STORAGE_DISK', 'public'),
    'base_path' => 'images',

    'quality' => [
        'webp' => (int) env('IMAGE_WEBP_QUALITY', 80),
        'avif' => (int) env('IMAGE_AVIF_QUALITY', 60),
    ],

    'generate_avif' => (bool) env('IMAGE_GENERATE_AVIF', true),

    'sizes' => [
        'thumb' => [
            'mode' => 'cover',
            'width' => 150,
            'height' => 150,
        ],
        'medium' => [
            'mode' => 'max_width',
            'width' => 600,
        ],
        'large' => [
            'mode' => 'max_width',
            'width' => 1200,
        ],
    ],

    'profiles' => [
        'branding_asset' => [
            'sizes' => [],
            'generate_avif' => false,
            'store_original' => true,
            'preferred_size' => 'original',
        ],
        'public_hero' => [
            'sizes' => [
                'medium' => [
                    'mode' => 'max_width',
                    'width' => 600,
                ],
                'large' => [
                    'mode' => 'max_width',
                    'width' => 1200,
                ],
            ],
            'generate_avif' => false,
            'store_original' => true,
            'preferred_size' => 'medium',
        ],
        'public_card' => [
            'sizes' => [
                'thumb' => [
                    'mode' => 'cover',
                    'width' => 150,
                    'height' => 150,
                ],
                'medium' => [
                    'mode' => 'max_width',
                    'width' => 600,
                ],
            ],
            'generate_avif' => false,
            'store_original' => true,
            'preferred_size' => 'thumb',
        ],
        'public_doctor' => [
            'sizes' => [
                'thumb' => [
                    'mode' => 'cover',
                    'width' => 150,
                    'height' => 150,
                ],
                'medium' => [
                    'mode' => 'max_width',
                    'width' => 600,
                ],
            ],
            'generate_avif' => false,
            'store_original' => true,
            'preferred_size' => 'thumb',
        ],
    ],

    'legacy_folder_map' => [
        'avatars/' => 'users',
        'landing/slides/' => 'banners',
        'landing/doctores/' => 'doctors',
    ],

    'fallbacks' => [
        'default' => 'img/placeholders/default.svg',
        'user' => 'img/placeholders/user.svg',
        'doctor' => 'img/placeholders/doctor.svg',
        'patient' => 'img/placeholders/patient.svg',
        'banner' => 'img/placeholders/banner.svg',
    ],
];
