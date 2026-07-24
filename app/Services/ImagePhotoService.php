<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImagePhotoService
{
    private const MAX_DIMENSION = 1280;

    private const JPEG_QUALITY = 80;

    private const PNG_COMPRESSION = 6;

    public function storeCompressed(UploadedFile $file, string $directory): string
    {
        $source = imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($source === false) {
            return $file->store($directory, 'public');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        // ponytail: bundled GD di sandbox ini tanpa libjpeg -- deteksi dukungan
        // JPEG saat runtime, fallback PNG. Environment lain (mis. production)
        // mungkin punya libjpeg dan bakal otomatis pakai JPEG (lebih kecil).
        $supportsJpeg = (bool) (gd_info()['JPEG Support'] ?? false);
        $extension = $supportsJpeg ? 'jpg' : 'png';

        ob_start();
        if ($supportsJpeg) {
            imagejpeg($canvas, null, self::JPEG_QUALITY);
        } else {
            imagepng($canvas, null, self::PNG_COMPRESSION);
        }
        $contents = ob_get_clean();
        imagedestroy($canvas);

        $path = $directory.'/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $contents);

        return $path;
    }
}
