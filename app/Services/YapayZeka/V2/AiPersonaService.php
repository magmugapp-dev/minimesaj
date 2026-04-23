<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiPersonaProfile;
use App\Models\User;
use App\Support\Language;

class AiPersonaService
{
    public function __construct(private ?AiEngineConfigService $engineConfigService = null)
    {
        $this->engineConfigService ??= app(AiEngineConfigService::class);
    }

    public function ensureForUser(User $aiUser): AiPersonaProfile
    {
        $aiUser->loadMissing('aiAyar');
        $profile = $aiUser->aiPersonaProfile()
            ->with(['engineConfig', 'guardrailRules'])
            ->first();

        if ($profile) {
            $defaults = [];

            if (!$profile->ai_engine_config_id) {
                $defaults['ai_engine_config_id'] = $this->engineConfigService->activeConfig()->id;
            }

            if (!$profile->ana_dil_kodu) {
                $defaults['ana_dil_kodu'] = Language::normalizeCode($aiUser->dil) ?: 'tr';
                $defaults['ana_dil_adi'] = Language::name($defaults['ana_dil_kodu']);
            }

            if ($defaults !== []) {
                $profile->forceFill($defaults)->save();
            }

            return $profile->fresh(['engineConfig', 'guardrailRules']);
        }

        $legacy = $aiUser->aiAyar;
        $profile = AiPersonaProfile::query()->create([
            'ai_user_id' => $aiUser->id,
            'ai_engine_config_id' => $this->engineConfigService->activeConfig()->id,
            'aktif_mi' => (bool) ($legacy?->aktif_mi ?? true),
            'dating_aktif_mi' => true,
            'instagram_aktif_mi' => true,
            'ilk_mesaj_atar_mi' => (bool) ($legacy?->ilk_mesaj_atar_mi ?? true),
            'ilk_mesaj_tonu' => $legacy?->ilk_mesaj_sablonu,
            'persona_ozeti' => $legacy?->kisilik_aciklamasi ?: $legacy?->kisilik_tipi ?: $aiUser->biyografi,
            'ana_dil_kodu' => Language::normalizeCode($aiUser->dil) ?: 'tr',
            'ana_dil_adi' => Language::name($aiUser->dil ?: 'tr'),
            'persona_ulke' => $aiUser->ulke,
            'persona_sehir' => $aiUser->il,
            'konusma_tonu' => $legacy?->konusma_tonu ?: 'dogal',
            'konusma_stili' => $legacy?->konusma_stili ?: 'samimi',
            'mizah_seviyesi' => (int) ($legacy?->mizah_seviyesi ?? 5),
            'flort_seviyesi' => (int) ($legacy?->flort_seviyesi ?? 4),
            'emoji_seviyesi' => (int) ($legacy?->emoji_seviyesi ?? 3),
            'giriskenlik_seviyesi' => (int) ($legacy?->giriskenlik_seviyesi ?? 5),
            'utangaclik_seviyesi' => (int) ($legacy?->utangaclik_seviyesi ?? 3),
            'duygusallik_seviyesi' => (int) ($legacy?->duygusallik_seviyesi ?? 5),
            'mesaj_uzunlugu_min' => max(12, (int) ($legacy?->mesaj_uzunlugu_min ?? 18)),
            'mesaj_uzunlugu_max' => max(80, (int) ($legacy?->mesaj_uzunlugu_max ?? 220)),
            'minimum_cevap_suresi_saniye' => (int) ($legacy?->minimum_cevap_suresi_saniye ?? 4),
            'maksimum_cevap_suresi_saniye' => (int) ($legacy?->maksimum_cevap_suresi_saniye ?? 24),
            'saat_dilimi' => $legacy?->saat_dilimi ?: config('app.timezone'),
            'uyku_baslangic' => $legacy?->uyku_baslangic ?: '01:00',
            'uyku_bitis' => $legacy?->uyku_bitis ?: '08:00',
            'hafta_sonu_uyku_baslangic' => $legacy?->hafta_sonu_uyku_baslangic ?: '02:00',
            'hafta_sonu_uyku_bitis' => $legacy?->hafta_sonu_uyku_bitis ?: '10:00',
        ]);

        return $profile->fresh(['engineConfig', 'guardrailRules']);
    }

    public function isChannelActive(AiPersonaProfile $profile, string $kanal): bool
    {
        if (!$profile->aktif_mi) {
            return false;
        }

        return match ($kanal) {
            'instagram' => (bool) $profile->instagram_aktif_mi,
            default => (bool) $profile->dating_aktif_mi,
        };
    }
}
