<?php

namespace App\Services\Notifications;

use App\Models\PushDeviceToken;
use App\Notifications\Messages\FcmMessage;
use App\Services\AyarServisi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class FcmService
{
    public function __construct(private AyarServisi $ayarServisi) {}

    public function diagnostics(): array
    {
        $serviceAccountDetails = $this->serviceAccountPathDetails();
        $serviceAccountPath = $serviceAccountDetails['path'];
        $projectId = $this->projectId();

        $diagnostics = [
            'configured' => $serviceAccountPath !== null && $projectId !== null,
            'project_id' => $projectId,
            'service_account_path' => $serviceAccountPath,
            'google_application_credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
            'checked_service_account_paths' => $serviceAccountDetails['checked'],
            'recommended_service_account_setting' => 'ayarlar/firebase/service-account.json',
            'recommended_service_account_path' => storage_path('app/private/ayarlar/firebase/service-account.json'),
            'access_token_received' => false,
        ];

        if ($serviceAccountPath === null) {
            $checked = $serviceAccountDetails['checked'] !== []
                ? implode(', ', $serviceAccountDetails['checked'])
                : 'no configured path';

            $diagnostics['error'] = 'Firebase service account JSON file was not found. Checked: ' . $checked . '.';

            return $diagnostics;
        }

        if ($projectId === null) {
            $diagnostics['error'] = 'Firebase project id is missing.';

            return $diagnostics;
        }

        try {
            $diagnostics['access_token_received'] = $this->accessToken() !== '';
        } catch (Throwable $exception) {
            $diagnostics['error'] = $exception->getMessage();
        }

        return $diagnostics;
    }

    public function sendToTokens(array $tokens, FcmMessage $message): void
    {
        try {
            if ($tokens === [] || !$this->configured()) {
                return;
            }

            $projectId = $this->projectId();
            $accessToken = $this->accessToken();

            foreach ($tokens as $token) {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $message->title,
                                'body' => $message->body,
                                'image' => $message->imageUrl,
                            ],
                            'data' => $message->data,
                            'android' => [
                                'priority' => 'high',
                            ],
                            'apns' => [
                                'headers' => [
                                    'apns-priority' => '10',
                                ],
                                'payload' => [
                                    'aps' => [
                                        'sound' => 'default',
                                        'content-available' => 1,
                                    ],
                                ],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    continue;
                }

                if ($this->tokenGecersiz($response->json(), $response->status())) {
                    PushDeviceToken::query()
                        ->where('token', $token)
                        ->delete();
                }

                Log::warning('FCM bildirimi gonderilemedi.', [
                    'status' => $response->status(),
                    'token' => $token,
                    'body' => $response->json(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('FCM servisi calisamadi.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function configured(): bool
    {
        return $this->serviceAccountPath() !== null && $this->projectId() !== null;
    }

    private function accessToken(): string
    {
        return Cache::remember('notifications.fcm.access_token', now()->addMinutes(50), function () {
            $serviceAccount = $this->serviceAccount();
            $assertion = $this->jwtOlustur($serviceAccount);

            $response = Http::asForm()->post((string) ($serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('FCM access token alinamadi.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function projectId(): ?string
    {
        $projectId = $this->settingString(
            'firebase_project_id',
            config('services.firebase.project_id')
        );

        if ($projectId !== null) {
            return $projectId;
        }

        if ($this->serviceAccountPath() === null) {
            return null;
        }

        $serviceAccount = $this->serviceAccount();

        $fromAccount = $serviceAccount['project_id'] ?? null;

        return is_string($fromAccount) && trim($fromAccount) !== ''
            ? trim($fromAccount)
            : null;
    }

    private function serviceAccount(): array
    {
        static $cached = null;

        if (is_array($cached)) {
            return $cached;
        }

        $path = $this->serviceAccountPath();

        if ($path === null) {
            throw new RuntimeException('FCM service account dosyasi bulunamadi.');
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('FCM service account verisi gecersiz.');
        }

        return $cached = $decoded;
    }

    private function serviceAccountPath(): ?string
    {
        return $this->serviceAccountPathDetails()['path'];
    }

    private function serviceAccountPathDetails(): array
    {
        $checked = [];
        $fromEnvironment = env('GOOGLE_APPLICATION_CREDENTIALS');

        if (is_string($fromEnvironment) && trim($fromEnvironment) !== '') {
            $environmentPath = trim($fromEnvironment);
            $checked[] = $environmentPath;
            $resolved = $this->resolvePath($environmentPath);

            if ($resolved !== null) {
                return ['path' => $resolved, 'checked' => $checked];
            }
        }

        $setting = $this->settingString(
            'firebase_service_account_path',
            config('services.firebase.service_account_path')
        );

        if ($setting !== null) {
            $checked[] = $setting;
            $resolved = $this->resolvePath($setting);

            if ($resolved !== null) {
                return ['path' => $resolved, 'checked' => $checked];
            }
        }

        return ['path' => null, 'checked' => $checked];
    }

    private function settingString(string $anahtar, mixed $fallback = null): ?string
    {
        $value = $this->ayarServisi->al($anahtar);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_string($fallback) && trim($fallback) !== '') {
            return trim($fallback);
        }

        return null;
    }

    private function resolvePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $basePath = base_path($path);

        if (is_file($basePath)) {
            return $basePath;
        }

        $storagePath = Storage::disk('local')->path($path);

        return is_file($storagePath) ? $storagePath : null;
    }

    private function jwtOlustur(array $serviceAccount): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(55)->timestamp,
        ], JSON_THROW_ON_ERROR));

        $signingInput = $header . '.' . $payload;
        $signature = '';
        $privateKey = openssl_pkey_get_private((string) ($serviceAccount['private_key'] ?? ''));

        if ($privateKey === false || !openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM JWT imzalanamadi.');
        }

        openssl_free_key($privateKey);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    private function tokenGecersiz(array $body, int $status): bool
    {
        if ($status === 404) {
            return true;
        }

        $errorCode = $body['error']['details'][0]['errorCode'] ?? null;

        return in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
