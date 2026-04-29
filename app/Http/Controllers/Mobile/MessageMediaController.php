<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Mesaj;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MessageMediaController extends Controller
{
    public function __invoke(Request $request, Mesaj $message): BinaryFileResponse
    {
        $message->loadMissing('sohbet.eslesme');
        $userId = (int) $request->user()->id;
        $match = $message->sohbet?->eslesme;

        abort_unless(
            $match && in_array($userId, [(int) $match->user_id, (int) $match->eslesen_user_id], true),
            403,
        );

        $relativePath = $this->relativePublicPath((string) $message->dosya_yolu);
        abort_if($relativePath === null || ! Storage::disk('public')->exists($relativePath), 404);

        $absolutePath = Storage::disk('public')->path($relativePath);
        $mime = Storage::disk('public')->mimeType($relativePath) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function relativePublicPath(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $path = parse_url($trimmed, PHP_URL_PATH) ?: $trimmed;
        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        $path = ltrim($path, '/\\');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }
}
