<?php

namespace App\Http\Controllers\Kimlik;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kimlik\GirisRequest;
use App\Http\Requests\Kimlik\KayitRequest;
use App\Http\Resources\KullaniciResource;
use App\Models\User;
use App\Services\Kimlik\AuthPuanServisi;
use App\Services\Kimlik\IstemciYetenekServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class KimlikController extends Controller
{
    public function __construct(
        private IstemciYetenekServisi $istemciYetenekServisi,
        private AuthPuanServisi $authPuanServisi,
    ) {}

    public function kayit(KayitRequest $request): JsonResponse
    {
        $veri = $request->validated();

        $user = User::create([
            'ad' => $veri['ad'],
            'soyad' => $veri['soyad'] ?? null,
            'kullanici_adi' => $veri['kullanici_adi'],
            'email' => $veri['email'] ?? null,
            'password' => $veri['password'],
            'cinsiyet' => $veri['cinsiyet'] ?? 'belirtmek_istemiyorum',
            'dogum_yili' => $veri['dogum_yili'] ?? null,
            'ulke' => $veri['ulke'] ?? null,
            'il' => $veri['il'] ?? null,
        ]);

        $this->authPuanServisi->kayitBonusuUygula($user);

        $yetenekler = $this->istemciYetenekServisi->belirle($veri['istemci_tipi']);
        $token = $user->createToken($veri['istemci_tipi'], $yetenekler);

        return response()->json([
            'kullanici' => new KullaniciResource($user->fresh()),
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function giris(GirisRequest $request): JsonResponse
    {
        $veri = $request->validated();

        $user = User::where('kullanici_adi', $veri['kullanici_adi'])->first();

        if (!$user || !Hash::check($veri['password'], $user->password)) {
            throw ValidationException::withMessages([
                'kullanici_adi' => ['Kimlik bilgileri hatali.'],
            ]);
        }

        if ($user->hesap_durumu !== 'aktif') {
            throw ValidationException::withMessages([
                'kullanici_adi' => ['Hesap aktif degil.'],
            ]);
        }

        $this->authPuanServisi->gunlukGirisBonusuUygula($user);

        $yetenekler = $this->istemciYetenekServisi->belirle($veri['istemci_tipi']);
        $token = $user->createToken($veri['istemci_tipi'], $yetenekler);

        return response()->json([
            'kullanici' => new KullaniciResource($user->fresh()),
            'token' => $token->plainTextToken,
        ]);
    }

    public function cikis(Request $request): JsonResponse
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return response()->json(['mesaj' => 'Cikis yapildi.']);
    }

    public function ben(Request $request): KullaniciResource
    {
        $user = $request->user();
        $this->authPuanServisi->gunlukGirisBonusuUygula($user);

        return new KullaniciResource($user->fresh()->load('aiCharacter', 'fotograflar'));
    }

    public function hesapSil(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['mesaj' => 'Hesap silindi.']);
    }
}
