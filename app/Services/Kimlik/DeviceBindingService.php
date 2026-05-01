<?php

namespace App\Services\Kimlik;

use App\Models\DeviceBinding;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeviceBindingService
{
    public function ensureAvailableForRegistration(?string $fingerprint): void
    {
        $fingerprint = $this->normalizeFingerprint($fingerprint);
        if (!$fingerprint) {
            return;
        }

        $binding = DeviceBinding::query()->where('device_fingerprint', $fingerprint)->first();
        if (!$binding) {
            return;
        }

        if ($binding->banned) {
            $this->reject('Bu cihazla yeni hesap acilamaz.');
        }

        if ($binding->user_id) {
            $this->reject('Bu cihaz zaten baska bir hesaba bagli.');
        }
    }

    public function bindOrFail(?string $fingerprint, User $user, ?string $platform = null): void
    {
        $fingerprint = $this->normalizeFingerprint($fingerprint);
        if (!$fingerprint) {
            return;
        }

        $binding = DeviceBinding::query()->firstOrNew([
            'device_fingerprint' => $fingerprint,
        ]);

        if ($binding->exists && $binding->banned) {
            $this->reject('Bu cihazla giris yapilamaz.');
        }

        if ($binding->exists && $binding->user_id && (int) $binding->user_id !== (int) $user->id) {
            $this->reject('Bu cihaz zaten baska bir hesaba bagli.');
        }

        $binding->forceFill([
            'user_id' => $user->id,
            'platform' => $this->normalizePlatform($platform),
            'banned' => false,
            'banned_at' => null,
            'bound_at' => $binding->bound_at ?: now(),
        ])->save();
    }

    public function banUserDevices(User $user): void
    {
        DeviceBinding::query()
            ->where('user_id', $user->id)
            ->update([
                'banned' => true,
                'banned_at' => now(),
            ]);
    }

    private function normalizeFingerprint(?string $fingerprint): ?string
    {
        $fingerprint = trim((string) $fingerprint);

        return $fingerprint !== '' ? mb_substr($fingerprint, 0, 255) : null;
    }

    private function normalizePlatform(?string $platform): ?string
    {
        $platform = trim((string) $platform);

        return $platform !== '' ? mb_substr($platform, 0, 20) : null;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages([
            'device_fingerprint' => [$message],
        ]);
    }
}
