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
        $legacy = $aiUser->aiAyar;
        $profile = $aiUser->aiPersonaProfile()
            ->with(['engineConfig', 'guardrailRules'])
            ->first();

        if ($profile) {
            $defaults = $this->missingDefaults($profile, $aiUser, $legacy);

            if ($defaults !== []) {
                $profile->forceFill($defaults)->save();
            }

            return $profile->fresh(['engineConfig', 'guardrailRules']);
        }

        $defaults = $this->behaviorDefaults();
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
            'mizah_seviyesi' => (int) ($legacy?->mizah_seviyesi ?? $defaults['mizah_seviyesi']),
            'flort_seviyesi' => (int) ($legacy?->flort_seviyesi ?? $defaults['flort_seviyesi']),
            'emoji_seviyesi' => (int) ($legacy?->emoji_seviyesi ?? $defaults['emoji_seviyesi']),
            'giriskenlik_seviyesi' => (int) ($legacy?->giriskenlik_seviyesi ?? $defaults['giriskenlik_seviyesi']),
            'utangaclik_seviyesi' => (int) ($legacy?->utangaclik_seviyesi ?? $defaults['utangaclik_seviyesi']),
            'duygusallik_seviyesi' => (int) ($legacy?->duygusallik_seviyesi ?? $defaults['duygusallik_seviyesi']),
            'argo_seviyesi' => (int) ($legacy?->argo_seviyesi ?? $defaults['argo_seviyesi']),
            'sicaklik_seviyesi' => (int) $defaults['sicaklik_seviyesi'],
            'empati_seviyesi' => (int) $defaults['empati_seviyesi'],
            'merak_seviyesi' => (int) $defaults['merak_seviyesi'],
            'ozguven_seviyesi' => (int) $defaults['ozguven_seviyesi'],
            'sabir_seviyesi' => (int) $defaults['sabir_seviyesi'],
            'baskinlik_seviyesi' => (int) $defaults['baskinlik_seviyesi'],
            'sarkastiklik_seviyesi' => (int) $defaults['sarkastiklik_seviyesi'],
            'romantizm_seviyesi' => (int) $defaults['romantizm_seviyesi'],
            'oyunculuk_seviyesi' => (int) $defaults['oyunculuk_seviyesi'],
            'ciddiyet_seviyesi' => (int) $defaults['ciddiyet_seviyesi'],
            'gizem_seviyesi' => (int) $defaults['gizem_seviyesi'],
            'hassasiyet_seviyesi' => (int) $defaults['hassasiyet_seviyesi'],
            'enerji_seviyesi' => (int) $defaults['enerji_seviyesi'],
            'kiskanclik_seviyesi' => (int) ($legacy?->kiskanclik_seviyesi ?? $defaults['kiskanclik_seviyesi']),
            'zeka_seviyesi' => (int) ($legacy?->zeka_seviyesi ?? $defaults['zeka_seviyesi']),
            'mesaj_uzunlugu_min' => max(12, (int) ($legacy?->mesaj_uzunlugu_min ?? 18)),
            'mesaj_uzunlugu_max' => max(80, (int) ($legacy?->mesaj_uzunlugu_max ?? 220)),
            'minimum_cevap_suresi_saniye' => (int) ($legacy?->minimum_cevap_suresi_saniye ?? 4),
            'maksimum_cevap_suresi_saniye' => (int) ($legacy?->maksimum_cevap_suresi_saniye ?? 24),
            'saat_dilimi' => $legacy?->saat_dilimi ?: config('app.timezone'),
            'uyku_baslangic' => $legacy?->uyku_baslangic ?: '01:00',
            'uyku_bitis' => $legacy?->uyku_bitis ?: '08:00',
            'hafta_sonu_uyku_baslangic' => $legacy?->hafta_sonu_uyku_baslangic ?: '02:00',
            'hafta_sonu_uyku_bitis' => $legacy?->hafta_sonu_uyku_bitis ?: '10:00',
            'metadata' => [
                'model_adi' => $legacy?->model_adi ?: $this->engineConfigService->activeConfig()->model_adi,
            ],
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

    private function missingDefaults(AiPersonaProfile $profile, User $aiUser, mixed $legacy): array
    {
        $defaults = [];
        $behaviorDefaults = $this->behaviorDefaults();

        if (!$profile->ai_engine_config_id) {
            $defaults['ai_engine_config_id'] = $this->engineConfigService->activeConfig()->id;
        }

        if (!$profile->ana_dil_kodu) {
            $defaults['ana_dil_kodu'] = Language::normalizeCode($aiUser->dil) ?: 'tr';
            $defaults['ana_dil_adi'] = Language::name($defaults['ana_dil_kodu']);
        }

        if (!data_get($profile->metadata, 'model_adi')) {
            $defaults['metadata'] = array_merge(
                $profile->metadata ?? [],
                ['model_adi' => $legacy?->model_adi ?: $this->engineConfigService->activeConfig()->model_adi]
            );
        }

        foreach ($behaviorDefaults as $field => $default) {
            if ($profile->{$field} !== null) {
                continue;
            }

            $legacyField = match ($field) {
                'kiskanclik_seviyesi', 'zeka_seviyesi', 'mizah_seviyesi', 'flort_seviyesi',
                'emoji_seviyesi', 'giriskenlik_seviyesi', 'utangaclik_seviyesi',
                'duygusallik_seviyesi', 'argo_seviyesi' => $field,
                default => null,
            };

            $defaults[$field] = $legacyField && isset($legacy?->{$legacyField})
                ? (int) $legacy->{$legacyField}
                : (int) $default;
        }

        return $defaults;
    }

    private function behaviorDefaults(): array
    {
        return collect(config('ai_studio_dropdowns.behavior_sliders', []))
            ->mapWithKeys(fn (array $meta, string $field) => [$field => (int) ($meta['default'] ?? 5)])
            ->all();
    }
}
