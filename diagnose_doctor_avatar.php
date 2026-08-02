<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Storage;

echo "=== DIAGNOSIS FOR DOCTOR AVATAR ===" . PHP_EOL;

$user = User::where('email', 'alejandroucenriquez@gmail.com')->first();

if (! $user) {
    echo "USER NOT FOUND!" . PHP_EOL;
    exit(1);
}

echo "User ID: " . $user->id . PHP_EOL;
echo "User Role: " . $user->role . PHP_EOL;
echo "Raw 'avatar' in DB: " . json_encode($user->getRawOriginal('avatar')) . PHP_EOL;
echo "Accessor \$user->avatar: " . json_encode($user->avatar) . PHP_EOL;
echo "Effective avatar_disk: " . config('image_optimization.avatar_disk') . PHP_EOL;

echo "Url ('thumb'): " . $user->avatarUrl('thumb') . PHP_EOL;
echo "Url ('medium'): " . $user->avatarUrl('medium') . PHP_EOL;
echo "Url ('original'): " . $user->avatarUrl('original') . PHP_EOL;

$diskName = config('image_optimization.avatar_disk');
$disk = Storage::disk($diskName);

$rawAvatar = $user->getRawOriginal('avatar');
if ($rawAvatar) {
    $dir = dirname($rawAvatar);
    echo "Directory: {$dir}" . PHP_EOL;
    echo "Exists rawAvatar: " . ($disk->exists($rawAvatar) ? 'YES' : 'NO') . PHP_EOL;
    echo "Exists thumb.webp: " . ($disk->exists($dir . '/thumb.webp') ? 'YES' : 'NO') . PHP_EOL;
    echo "Exists medium.webp: " . ($disk->exists($dir . '/medium.webp') ? 'YES' : 'NO') . PHP_EOL;

    try {
        $allFiles = $disk->allFiles($dir);
        echo "Files in dir on {$diskName}: " . json_encode($allFiles) . PHP_EOL;
    } catch (\Throwable $e) {
        echo "Error listing files in dir on {$diskName}: " . $e->getMessage() . PHP_EOL;
    }
} else {
    echo "User has no avatar in DB!" . PHP_EOL;
}
