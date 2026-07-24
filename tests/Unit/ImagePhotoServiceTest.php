<?php

namespace Tests\Unit;

use App\Services\ImagePhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagePhotoServiceTest extends TestCase
{
    private ImagePhotoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ImagePhotoService();
    }

    public function test_large_photo_is_resized_down_to_max_dimension(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('big.png', 2000, 1000);
        $path = $this->svc->storeCompressed($file, 'community');

        Storage::disk('public')->assertExists($path);

        $fullPath = Storage::disk('public')->path($path);
        [$width, $height] = getimagesize($fullPath);

        $this->assertEquals(1280, $width);
        $this->assertEquals(640, $height); // 2000x1000 (2:1) diskalakan ke 1280x640, aspect ratio terjaga
    }

    public function test_small_photo_is_not_upscaled(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('small.png', 200, 150);
        $path = $this->svc->storeCompressed($file, 'community');

        $fullPath = Storage::disk('public')->path($path);
        [$width, $height] = getimagesize($fullPath);

        $this->assertEquals(200, $width);
        $this->assertEquals(150, $height);
    }

    public function test_compressed_file_is_smaller_than_a_low_compression_baseline(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.png', 1920, 1920);
        $originalSize = filesize($file->getRealPath());

        $path = $this->svc->storeCompressed($file, 'community');
        $compressedSize = Storage::disk('public')->size($path);

        $this->assertLessThan($originalSize, $compressedSize);
    }

    public function test_stored_path_starts_with_given_directory(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('x.png', 300, 300);
        $path = $this->svc->storeCompressed($file, 'places');

        $this->assertStringStartsWith('places/', $path);
    }
}
