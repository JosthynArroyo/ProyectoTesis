<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ProfileAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_stores_webp_variants_and_removes_previous_avatar_after_success(): void
    {
        config(['image_optimization.avatar_disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('public');

        $user = User::factory()->create();
        $service = app(ProfileAvatarService::class);

        $file1 = UploadedFile::fake()->image('profile.png', 300, 300);
        $path1 = $service->replace($user, $file1);

        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($path1));

        $dir1 = dirname($path1);
        $this->assertTrue($disk->exists("{$dir1}/thumb.webp"));
        $this->assertTrue($disk->exists("{$dir1}/medium.webp"));

        // Replace avatar
        $file2 = UploadedFile::fake()->image('profile2.png', 300, 300);
        $path2 = $service->replace($user, $file2);

        $this->assertTrue($disk->exists($path2));
        $this->assertFalse($disk->exists($path1));
    }
}
