<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;

class ProfileAvatarService
{
    private const AVATAR_SIZES = [
        'thumb' => [
            'mode' => 'cover',
            'width' => 150,
            'height' => 150,
        ],
        'medium' => [
            'mode' => 'max_width',
            'width' => 480,
        ],
        'large' => [
            'mode' => 'max_width',
            'width' => 720,
        ],
    ];

    public function __construct(private readonly ImageOptimizer $optimizer)
    {
    }

    public function replace(User $user, UploadedFile $file, string $folder): string
    {
        $previousAvatar = $user->avatar;

        $newAvatar = $this->optimizer->optimizeAndStore(
            $file,
            $folder,
            self::AVATAR_SIZES,
            generateAvif: false
        );

        if (
            $previousAvatar
            && $this->optimizer->normalizeStoredPath($previousAvatar) !== $this->optimizer->normalizeStoredPath($newAvatar)
        ) {
            $this->optimizer->deleteByStoredPath($previousAvatar, $folder);
        }

        return $newAvatar;
    }
}
