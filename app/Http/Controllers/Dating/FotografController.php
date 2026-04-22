<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\FotografGuncelleRequest;
use App\Http\Requests\Dating\FotografYukleRequest;
use App\Http\Resources\FotografResource;
use App\Models\UserFotografi;
use App\Services\AyarServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class FotografController extends Controller
{
    public function __construct(private AyarServisi $ayarServisi) {}

    public function index(Request $request): JsonResponse
    {
        return FotografResource::collection(
            $request->user()->fotograflar()->orderBy('sira_no')->get()
        )->response();
    }

    public function store(FotografYukleRequest $request): JsonResponse
    {
        $user = $request->user();
        $dosya = $request->file('dosya');

        try {
            if ($user->fotograflar()->count() >= 6) {
                throw ValidationException::withMessages([
                    'dosya' => ['En fazla 6 medya yukleyebilirsin.'],
                ]);
            }

            $mimeTipi = $dosya?->getMimeType() ?: $dosya?->getClientMimeType();
            $medyaTipi = $this->medyaTipiniBelirle($dosya?->getClientOriginalName() ?? '', $mimeTipi);

            // Storage path'ı medya tipine göre seç (config'ten)
            $storagePath = $medyaTipi === 'video'
                ? (string) $this->ayarServisi->al('depolama_video_dizini', config('storage.paths.profile_videos'))
                : (string) $this->ayarServisi->al('depolama_fotograf_dizini', config('storage.paths.profile_photos'));

            $disk = (string) $this->ayarServisi->al('depolama_disk', config('storage.disk'));
            $yol = $dosya->store($storagePath . '/' . $user->id, $disk);
            $fotografSayisi = $user->fotograflar()->count();
            $fotografVarMi = $user->fotograflar()->where('medya_tipi', 'fotograf')->exists();
            $anaFotografMi = $medyaTipi === 'fotograf'
                && ($request->boolean('ana_fotograf_mi') || !$fotografVarMi);

            $fotograf = DB::transaction(function () use ($user, $yol, $mimeTipi, $medyaTipi, $fotografSayisi, $anaFotografMi) {
                if ($anaFotografMi) {
                    $user->fotograflar()->where('medya_tipi', 'fotograf')->update([
                        'ana_fotograf_mi' => false,
                    ]);
                }

                $fotograf = $user->fotograflar()->create([
                    'dosya_yolu' => $yol,
                    'medya_tipi' => $medyaTipi,
                    'mime_tipi' => $mimeTipi,
                    'sira_no' => $fotografSayisi,
                    'ana_fotograf_mi' => $anaFotografMi,
                ]);

                if ($anaFotografMi) {
                    $user->forceFill([
                        'profil_resmi' => $yol,
                    ])->save();
                }

                return $fotograf;
            });

            return (new FotografResource($fotograf))
                ->response()
                ->setStatusCode(201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Profil medya yukleme basarisiz', [
                'user_id' => $user->id,
                'dosya_adi' => $dosya?->getClientOriginalName(),
                'mime_tipi' => $dosya?->getMimeType(),
                'boyut' => $dosya?->getSize(),
                'hata' => $exception->getMessage(),
                'sinif' => $exception::class,
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'mesaj' => 'Medya yuklenirken sunucuda beklenmeyen bir hata olustu.',
            ], 500);
        }
    }

    public function update(FotografGuncelleRequest $request, UserFotografi $fotograf): FotografResource
    {
        Gate::authorize('guncelle', $fotograf);

        $veri = $request->validated();
        $user = $request->user();

        $fotograf = DB::transaction(function () use ($veri, $user, $fotograf) {
            if (($veri['ana_fotograf_mi'] ?? false) === true) {
                if ($fotograf->medya_tipi !== 'fotograf') {
                    throw ValidationException::withMessages([
                        'ana_fotograf_mi' => ['Profil resmi olarak sadece fotograf secilebilir.'],
                    ]);
                }

                $user->fotograflar()
                    ->where('medya_tipi', 'fotograf')
                    ->where('id', '!=', $fotograf->id)
                    ->update(['ana_fotograf_mi' => false]);

                $fotograf->forceFill([
                    'ana_fotograf_mi' => true,
                ])->save();

                $user->forceFill([
                    'profil_resmi' => $fotograf->dosya_yolu,
                ])->save();
            }

            if (array_key_exists('sira_no', $veri)) {
                $fotograf->forceFill([
                    'sira_no' => $veri['sira_no'],
                ])->save();
            }

            return $fotograf->fresh();
        });

        return new FotografResource($fotograf);
    }

    public function destroy(Request $request, UserFotografi $fotograf): JsonResponse
    {
        Gate::authorize('sil', $fotograf);

        $user = $request->user();
        $dosyalar = array_values(array_filter([
            $fotograf->dosya_yolu,
            $fotograf->onizleme_yolu,
        ]));
        $silinenYol = $fotograf->dosya_yolu;
        $anaFotografMi = $fotograf->ana_fotograf_mi;
        $medyaTipi = $fotograf->medya_tipi;

        DB::transaction(function () use ($user, $fotograf, $silinenYol, $anaFotografMi, $medyaTipi) {
            $fotograf->delete();

            if ($anaFotografMi && $medyaTipi === 'fotograf') {
                $yeniAnaFotograf = $user->fotograflar()
                    ->where('medya_tipi', 'fotograf')
                    ->orderBy('sira_no')
                    ->first();

                if ($yeniAnaFotograf !== null) {
                    $user->fotograflar()
                        ->where('medya_tipi', 'fotograf')
                        ->update(['ana_fotograf_mi' => false]);

                    $yeniAnaFotograf->forceFill([
                        'ana_fotograf_mi' => true,
                    ])->save();

                    $user->forceFill([
                        'profil_resmi' => $yeniAnaFotograf->dosya_yolu,
                    ])->save();
                } elseif ($user->profil_resmi === $silinenYol) {
                    $user->forceFill([
                        'profil_resmi' => null,
                    ])->save();
                }
            }
        });

        if ($dosyalar !== []) {
            $disk = (string) $this->ayarServisi->al('depolama_disk', config('storage.disk'));
            Storage::disk($disk)->delete($dosyalar);
        }

        return response()->json(['mesaj' => 'Fotoğraf silindi.']);
    }

    private function medyaTipiniBelirle(string $orijinalAd, ?string $mimeTipi): string
    {
        if (str_starts_with((string) $mimeTipi, 'video/')) {
            return 'video';
        }

        $uzanti = strtolower(pathinfo($orijinalAd, PATHINFO_EXTENSION));
        $varsayilanVideoUzantilari = implode(',', config('storage.upload.allowed_video_formats'));
        $videoUzantilari = array_map(
            'trim',
            explode(',', strtolower((string) $this->ayarServisi->al('izinli_video_uzantilari', $varsayilanVideoUzantilari)))
        );

        return in_array($uzanti, $videoUzantilari, true) ? 'video' : 'fotograf';
    }
}
