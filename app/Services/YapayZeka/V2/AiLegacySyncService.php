<?php

namespace App\Services\YapayZeka\V2;

use App\Models\InstagramAiGorevi;
use App\Models\YapayZekaGorevi;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Services\YapayZeka\V2\Data\AiGenerationResult;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Carbon\CarbonInterface;
use Throwable;

class AiLegacySyncService
{
    public function syncQueued(AiTurnContext $context, CarbonInterface $plannedAt): void
    {
        if ($context->kanal === 'instagram' && $context->instagramMesaj) {
            InstagramAiGorevi::query()->updateOrCreate(
                ['instagram_mesaj_id' => $context->instagramMesaj->id],
                [
                    'instagram_hesap_id' => $context->instagramHesap?->id,
                    'instagram_kisi_id' => $context->instagramKisi?->id,
                    'durum' => 'bekliyor',
                    'deneme_sayisi' => 0,
                    'istek_baslatildi_at' => null,
                    'tamamlandi_at' => null,
                    'hata_mesaji' => null,
                ]
            );

            return;
        }

        if ($context->gelenMesaj && $context->sohbet) {
            YapayZekaGorevi::query()->updateOrCreate(
                [
                    'gelen_mesaj_id' => $context->gelenMesaj->id,
                    'ai_user_id' => $context->aiUser->id,
                ],
                [
                    'sohbet_id' => $context->sohbet->id,
                    'durum' => 'bekliyor',
                    'deneme_sayisi' => 0,
                    'tamamlandi_at' => null,
                    'istek_baslatildi_at' => null,
                    'son_parca_at' => null,
                    'hata_mesaji' => null,
                    'model_adi' => $context->aiUser->aiAyar?->model_adi ?? GeminiSaglayici::MODEL_ADI,
                    'saglayici_tipi' => $context->aiUser->aiAyar?->saglayici_tipi ?? 'gemini',
                ]
            );
        }
    }

    public function syncStarted(AiTurnContext $context): void
    {
        $values = [
            'durum' => 'istek_gonderildi',
            'istek_baslatildi_at' => now(),
            'tamamlandi_at' => null,
            'hata_mesaji' => null,
        ];

        if ($context->kanal === 'instagram' && $context->instagramMesaj) {
            InstagramAiGorevi::query()
                ->where('instagram_mesaj_id', $context->instagramMesaj->id)
                ->update($values);

            return;
        }

        if ($context->gelenMesaj) {
            YapayZekaGorevi::query()
                ->where('gelen_mesaj_id', $context->gelenMesaj->id)
                ->where('ai_user_id', $context->aiUser->id)
                ->update($values);
        }
    }

    public function syncCompleted(
        AiTurnContext $context,
        AiGenerationResult $result,
        int $latencyMs,
    ): void {
        $values = [
            'durum' => 'tamamlandi',
            'cevap_metni' => $result->replyText,
            'model_adi' => $result->model,
            'giris_token_sayisi' => $result->inputTokens ?: null,
            'cikis_token_sayisi' => $result->outputTokens ?: null,
            'tamamlandi_at' => now(),
            'son_parca_at' => now(),
            'yanit_suresi_ms' => $latencyMs,
            'hata_mesaji' => null,
        ];

        if ($context->kanal === 'instagram' && $context->instagramMesaj) {
            unset($values['giris_token_sayisi'], $values['cikis_token_sayisi']);

            InstagramAiGorevi::query()
                ->where('instagram_mesaj_id', $context->instagramMesaj->id)
                ->update($values);

            return;
        }

        if ($context->gelenMesaj) {
            YapayZekaGorevi::query()
                ->where('gelen_mesaj_id', $context->gelenMesaj->id)
                ->where('ai_user_id', $context->aiUser->id)
                ->update($values);
        }
    }

    public function syncSkipped(AiTurnContext $context, string $reason): void
    {
        $values = [
            'durum' => 'atlandi',
            'hata_mesaji' => $reason,
            'tamamlandi_at' => now(),
        ];

        if ($context->kanal === 'instagram' && $context->instagramMesaj) {
            InstagramAiGorevi::query()
                ->where('instagram_mesaj_id', $context->instagramMesaj->id)
                ->update($values);

            return;
        }

        if ($context->gelenMesaj) {
            YapayZekaGorevi::query()
                ->where('gelen_mesaj_id', $context->gelenMesaj->id)
                ->where('ai_user_id', $context->aiUser->id)
                ->update($values);
        }
    }

    public function syncFailed(AiTurnContext $context, Throwable $e, int $attempts): void
    {
        $values = [
            'durum' => 'basarisiz',
            'deneme_sayisi' => $attempts,
            'hata_mesaji' => mb_substr($e->getMessage(), 0, 1000),
            'tamamlandi_at' => now(),
        ];

        if ($context->kanal === 'instagram' && $context->instagramMesaj) {
            InstagramAiGorevi::query()
                ->where('instagram_mesaj_id', $context->instagramMesaj->id)
                ->update($values);

            return;
        }

        if ($context->gelenMesaj) {
            YapayZekaGorevi::query()
                ->where('gelen_mesaj_id', $context->gelenMesaj->id)
                ->where('ai_user_id', $context->aiUser->id)
                ->update($values);
        }
    }
}
