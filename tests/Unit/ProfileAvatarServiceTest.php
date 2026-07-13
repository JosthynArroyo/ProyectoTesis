<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ProfileAvatarService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarServiceTest extends TestCase
{
    public function test_replace_stores_webp_variants_and_removes_previous_avatar_after_success(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('images/users/thumb/old-avatar.webp', 'old-thumb');
        Storage::disk('public')->put('images/users/medium/old-avatar.webp', 'old-medium');
        Storage::disk('public')->put('images/users/large/old-avatar.webp', 'old-large');

        $user = new User([
            'avatar' => 'images/users/large/old-avatar.webp',
        ]);

        $newAvatar = app(ProfileAvatarService::class)->replace(
            $user,
            $this->fakePngUpload('profile.png'),
            'users'
        );

        $baseName = pathinfo($newAvatar, PATHINFO_FILENAME);

        if (str_ends_with($newAvatar, '.webp')) {
            $this->assertSame("images/users/large/{$baseName}.webp", $newAvatar);
            Storage::disk('public')->assertExists("images/users/thumb/{$baseName}.webp");
            Storage::disk('public')->assertExists("images/users/medium/{$baseName}.webp");
            Storage::disk('public')->assertExists("images/users/large/{$baseName}.webp");
            Storage::disk('public')->assertMissing("images/users/thumb/{$baseName}.avif");
        } else {
            $this->assertSame("images/users/original/{$baseName}.png", $newAvatar);
            Storage::disk('public')->assertExists($newAvatar);
        }

        Storage::disk('public')->assertMissing('images/users/thumb/old-avatar.webp');
        Storage::disk('public')->assertMissing('images/users/medium/old-avatar.webp');
        Storage::disk('public')->assertMissing('images/users/large/old-avatar.webp');
    }

    private function fakePngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 300, 300);
    }
}
