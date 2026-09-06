<?php

return [
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
];
