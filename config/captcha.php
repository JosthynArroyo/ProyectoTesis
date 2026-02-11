<?php

return [
    'vision_url' => env('VISION_URL', 'http://127.0.0.1:9001'),
    'confidence_threshold' => (float) env('CAPTCHA_CONFIDENCE_THRESHOLD', 0.75),
    'max_attempts' => (int) env('CAPTCHA_MAX_ATTEMPTS', 5),
    'expires_seconds' => (int) env('CAPTCHA_EXPIRES_SECONDS', 180),
    'classes' => [
        'giraffe',
        'horse',
        'koala',
        'kangaroo',
        'rhinoceros',
        'dolphin',
        'blue_whale',
        'zebra',
    ],
    'labels_es' => [
        'giraffe' => 'Jirafa',
        'horse' => 'Caballo',
        'koala' => 'Koala',
        'kangaroo' => 'Canguro',
        'rhinoceros' => 'Rinoceronte',
        'dolphin' => "Delf\u{00ED}n",
        'blue_whale' => 'Ballena',
        'zebra' => 'Cebra',
    ],
    'dataset_splits' => ['val', 'train'],
];
