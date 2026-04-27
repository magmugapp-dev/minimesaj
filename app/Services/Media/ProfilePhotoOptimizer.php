<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProfilePhotoOptimizer
{
    private const MAIN_MAX_EDGE = 1600;

    private const PREVIEW_MAX_EDGE = 480;

    private const MAIN_QUALITY = 86;

    private const PREVIEW_QUALITY = 78;

    public function optimizeAndStore(UploadedFile $file, string $disk, string $directory): array
    {
        $source = $this->createSourceImage($file);
        $source = $this->applyOrientation($source, $file);

        try {
            $main = $this->resizeToMaxEdge($source, self::MAIN_MAX_EDGE);
            $preview = $this->resizeToMaxEdge($source, self::PREVIEW_MAX_EDGE);

            $baseName = Str::uuid()->toString();
            $directory = trim($directory, '/');
            $mainPath = "{$directory}/{$baseName}.jpg";
            $previewPath = "{$directory}/{$baseName}_preview.jpg";

            $storedPaths = [];

            try {
                $this->storeJpeg($main, $disk, $mainPath, self::MAIN_QUALITY);
                $storedPaths[] = $mainPath;
                $this->storeJpeg($preview, $disk, $previewPath, self::PREVIEW_QUALITY);
            } catch (Throwable $exception) {
                Storage::disk($disk)->delete($storedPaths);

                throw $exception;
            }

            return [
                'dosya_yolu' => $mainPath,
                'onizleme_yolu' => $previewPath,
                'mime_tipi' => 'image/jpeg',
            ];
        } finally {
            if (isset($main) && $main !== $source) {
                imagedestroy($main);
            }

            if (isset($preview) && $preview !== $source) {
                imagedestroy($preview);
            }

            imagedestroy($source);
        }
    }

    private function createSourceImage(UploadedFile $file): \GdImage
    {
        $path = $file->getRealPath();
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'fotograflar' => ['Fotograf dosyasi okunamadi.'],
            ]);
        }

        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $image instanceof \GdImage) {
            throw ValidationException::withMessages([
                'fotograflar' => ['Sadece jpg, jpeg, png veya webp fotograf yuklenebilir.'],
            ]);
        }

        return $this->flattenToJpegCanvas($image);
    }

    private function applyOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
        $path = $file->getRealPath();

        if (! in_array($mimeType, ['image/jpeg', 'image/jpg'], true) || ! function_exists('exif_read_data') || ! is_string($path)) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($image, IMG_FLIP_VERTICAL), 270),
            6 => $this->rotate($image, 270),
            7 => $this->rotate($this->flip($image, IMG_FLIP_VERTICAL), 90),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function resizeToMaxEdge(\GdImage $source, int $maxEdge): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $largestEdge = max($width, $height);

        if ($largestEdge <= $maxEdge) {
            return $this->copyCanvas($source, $width, $height);
        }

        $ratio = $maxEdge / $largestEdge;
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = $this->blankCanvas($targetWidth, $targetHeight);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function flattenToJpegCanvas(\GdImage $source): \GdImage
    {
        $target = $this->blankCanvas(imagesx($source), imagesy($source));
        imagecopy($target, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
        imagedestroy($source);

        return $target;
    }

    private function copyCanvas(\GdImage $source, int $width, int $height): \GdImage
    {
        $target = $this->blankCanvas($width, $height);
        imagecopy($target, $source, 0, 0, 0, 0, $width, $height);

        return $target;
    }

    private function blankCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        return $canvas;
    }

    private function flip(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    private function rotate(\GdImage $image, int $angle): \GdImage
    {
        $rotated = imagerotate($image, $angle, imagecolorallocate($image, 255, 255, 255));

        if (! $rotated instanceof \GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function storeJpeg(\GdImage $image, string $disk, string $path, int $quality): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'ai-profile-photo-');

        if (! is_string($tempPath) || $tempPath === '') {
            throw new RuntimeException('Gecici fotograf dosyasi olusturulamadi.');
        }

        try {
            imageinterlace($image, true);

            if (! imagejpeg($image, $tempPath, $quality)) {
                throw new RuntimeException('Fotograf optimize edilemedi.');
            }

            $contents = file_get_contents($tempPath);

            if (! is_string($contents)) {
                throw new RuntimeException('Optimize edilen fotograf okunamadi.');
            }

            $stored = Storage::disk($disk)->put($path, $contents, [
                'visibility' => 'public',
            ]);

            if (! $stored) {
                throw new RuntimeException('Fotograf depolamaya yazilamadi.');
            }
        } finally {
            @unlink($tempPath);
        }
    }
}
