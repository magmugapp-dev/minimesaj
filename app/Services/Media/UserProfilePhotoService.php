<?php

namespace App\Services\Media;

use App\Models\User;
use App\Models\UserFotografi;
use App\Services\AyarServisi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserProfilePhotoService
{
    private const MAX_PHOTOS = 6;

    public function __construct(
        private AyarServisi $ayarServisi,
        private ProfilePhotoOptimizer $optimizer,
    ) {}

    public function upload(User $user, UploadedFile $file, ?bool $makePrimary = null): UserFotografi
    {
        if ($user->fotograflar()->count() >= self::MAX_PHOTOS) {
            throw ValidationException::withMessages([
                'fotograflar' => ['En fazla 6 fotograf yuklenebilir.'],
            ]);
        }

        $disk = $this->disk();
        $stored = $this->optimizer->optimizeAndStore(
            $file,
            $disk,
            $this->directory($user),
        );

        try {
            return DB::transaction(function () use ($user, $stored, $makePrimary): UserFotografi {
                $hasPrimaryPhoto = $user->fotograflar()
                    ->where('medya_tipi', 'fotograf')
                    ->where('ana_fotograf_mi', true)
                    ->exists();
                $shouldBePrimary = $makePrimary === true || ! $hasPrimaryPhoto;
                $nextOrder = ((int) $user->fotograflar()->max('sira_no')) + 1;

                if ($shouldBePrimary) {
                    $user->fotograflar()
                        ->where('medya_tipi', 'fotograf')
                        ->update(['ana_fotograf_mi' => false]);
                }

                $photo = $user->fotograflar()->create([
                    'dosya_yolu' => $stored['dosya_yolu'],
                    'onizleme_yolu' => $stored['onizleme_yolu'],
                    'medya_tipi' => 'fotograf',
                    'mime_tipi' => $stored['mime_tipi'],
                    'sira_no' => $nextOrder,
                    'ana_fotograf_mi' => $shouldBePrimary,
                    'aktif_mi' => true,
                ]);

                if ($shouldBePrimary) {
                    $user->forceFill([
                        'profil_resmi' => $photo->dosya_yolu,
                    ])->save();
                }

                return $photo->fresh();
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete(array_filter([
                $stored['dosya_yolu'] ?? null,
                $stored['onizleme_yolu'] ?? null,
            ]));

            throw $exception;
        }
    }

    public function update(User $user, UserFotografi $photo, array $data): UserFotografi
    {
        $this->ensureBelongsToUser($user, $photo);

        return DB::transaction(function () use ($user, $photo, $data): UserFotografi {
            if (array_key_exists('sira_no', $data)) {
                $photo->forceFill([
                    'sira_no' => (int) $data['sira_no'],
                ])->save();
            }

            if (array_key_exists('aktif_mi', $data)) {
                $isActive = (bool) $data['aktif_mi'];
                $photo->forceFill([
                    'aktif_mi' => $isActive,
                ])->save();

                if (! $isActive && $photo->ana_fotograf_mi) {
                    $photo->forceFill([
                        'ana_fotograf_mi' => false,
                    ])->save();
                    $this->assignFallbackPrimaryPhoto($user, $photo->dosya_yolu);
                }
            }

            if (($data['ana_fotograf_mi'] ?? false) === true) {
                if (! $photo->aktif_mi || $photo->medya_tipi !== 'fotograf') {
                    throw ValidationException::withMessages([
                        'ana_fotograf_mi' => ['Ana fotograf olarak sadece aktif fotograf secilebilir.'],
                    ]);
                }

                $user->fotograflar()
                    ->where('medya_tipi', 'fotograf')
                    ->where('id', '!=', $photo->id)
                    ->update(['ana_fotograf_mi' => false]);

                $photo->forceFill([
                    'ana_fotograf_mi' => true,
                ])->save();

                $user->forceFill([
                    'profil_resmi' => $photo->dosya_yolu,
                ])->save();
            }

            return $photo->fresh();
        });
    }

    public function delete(User $user, UserFotografi $photo): void
    {
        $this->ensureBelongsToUser($user, $photo);

        $disk = $this->disk();
        $paths = array_values(array_filter([
            $photo->dosya_yolu,
            $photo->onizleme_yolu,
        ]));
        $removedPath = $photo->dosya_yolu;
        $wasPrimary = $photo->ana_fotograf_mi;

        DB::transaction(function () use ($user, $photo, $removedPath, $wasPrimary): void {
            $photo->delete();

            if ($wasPrimary) {
                $this->assignFallbackPrimaryPhoto($user, $removedPath);
            }
        });

        if ($paths !== []) {
            Storage::disk($disk)->delete($paths);
        }
    }

    public function maxPhotos(): int
    {
        return self::MAX_PHOTOS;
    }

    private function assignFallbackPrimaryPhoto(User $user, ?string $removedPath = null): void
    {
        $fallback = $user->fotograflar()
            ->where('medya_tipi', 'fotograf')
            ->where('aktif_mi', true)
            ->orderBy('sira_no')
            ->orderBy('id')
            ->first();

        if ($fallback) {
            $user->fotograflar()
                ->where('medya_tipi', 'fotograf')
                ->update(['ana_fotograf_mi' => false]);

            $fallback->forceFill([
                'ana_fotograf_mi' => true,
            ])->save();

            $user->forceFill([
                'profil_resmi' => $fallback->dosya_yolu,
            ])->save();

            return;
        }

        if ($removedPath === null || $user->profil_resmi === $removedPath) {
            $user->forceFill([
                'profil_resmi' => null,
            ])->save();
        }
    }

    private function ensureBelongsToUser(User $user, UserFotografi $photo): void
    {
        if ((int) $photo->user_id !== (int) $user->id) {
            abort(404);
        }
    }

    private function disk(): string
    {
        return (string) $this->ayarServisi->al('depolama_disk', config('storage.disk', 'public'));
    }

    private function directory(User $user): string
    {
        $base = (string) $this->ayarServisi->al('depolama_fotograf_dizini', config('storage.paths.profile_photos', 'fotograflar'));

        return trim($base, '/').'/'.$user->id;
    }
}
