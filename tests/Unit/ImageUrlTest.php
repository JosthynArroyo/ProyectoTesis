<?php

namespace Tests\Unit;

use App\Support\ImageUrl;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUrlTest extends TestCase
{
    public function test_local_storage_variants_include_file_version(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('images/doctors/thumb/ana.webp', 'thumb');
        Storage::disk('public')->put('images/doctors/medium/ana.webp', 'medium');
        Storage::disk('public')->put('images/doctors/large/ana.webp', 'large');

        $variants = app(ImageUrl::class)->variants('images/doctors/large/ana.webp', 'doctors', 'doctor');

        $this->assertStringContainsString('/storage/images/doctors/thumb/ana.webp?v=', $variants['thumb']);
        $this->assertStringContainsString('/storage/images/doctors/medium/ana.webp?v=', $variants['medium']);
        $this->assertStringContainsString('/storage/images/doctors/large/ana.webp?v=', $variants['large']);
        $this->assertStringContainsString('/storage/images/doctors/thumb/ana.webp?v=', $variants['srcset']);
    }
}
