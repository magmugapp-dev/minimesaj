<?php

namespace App\Http\Requests\Dating;

use App\Services\AyarServisi;
use Illuminate\Foundation\Http\FormRequest;

class FotografYukleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ayarServisi = app(AyarServisi::class);

        $maxFotoBoyutMb = (int) $ayarServisi->al('max_foto_boyut_mb', config('storage.upload.max_image_size_mb'));
        $maxVideoBoyutMb = (int) $ayarServisi->al('max_video_boyut_mb', config('storage.upload.max_video_size_mb'));
        $maxFileSizeKb = max($maxFotoBoyutMb, $maxVideoBoyutMb, 1) * 1024;

        $varsayilanFotoUzantilari = implode(',', config('storage.upload.allowed_image_formats'));
        $varsayilanVideoUzantilari = implode(',', config('storage.upload.allowed_video_formats'));

        $fotoUzantilari = $this->csvToArray((string) $ayarServisi->al('izinli_fotograf_uzantilari', $varsayilanFotoUzantilari));
        $videoUzantilari = $this->csvToArray((string) $ayarServisi->al('izinli_video_uzantilari', $varsayilanVideoUzantilari));
        $allowedFormats = array_values(array_unique(array_merge($fotoUzantilari, $videoUzantilari)));
        $mimeFormats = implode(',', $allowedFormats);

        return [
            'dosya' => "required|file|max:{$maxFileSizeKb}|mimes:{$mimeFormats}",
            'ana_fotograf_mi' => 'sometimes|boolean',
        ];
    }

    private function csvToArray(string $csv): array
    {
        $parcalar = array_map('trim', explode(',', strtolower($csv)));

        return array_values(array_filter($parcalar, static fn(string $uzanti): bool => $uzanti !== ''));
    }
}
