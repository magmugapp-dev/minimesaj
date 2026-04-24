<?php

namespace App\Jobs\Concerns;

use App\Models\InstagramHesap;
use App\Models\InstagramMesaj;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\YapayZeka\V2\Data\AiTurnContext;

trait ResolvesAiTurnContext
{
    protected function resolveAiTurnContext(
        string $kanal,
        string $turnType,
        int $aiUserId,
        ?int $sohbetId = null,
        ?int $gelenMesajId = null,
        ?int $instagramHesapId = null,
        ?int $instagramMesajId = null,
    ): ?AiTurnContext {
        $aiUser = User::query()->find($aiUserId);
        if (!$aiUser || $aiUser->hesap_tipi !== 'ai') {
            return null;
        }

        if ($kanal === 'instagram') {
            $hesap = InstagramHesap::query()->find($instagramHesapId);
            $mesaj = InstagramMesaj::query()->with('kisi')->find($instagramMesajId);

            if (!$hesap || !$mesaj || !$mesaj->kisi) {
                return null;
            }

            return new AiTurnContext(
                kanal: 'instagram',
                turnType: $turnType,
                aiUser: $aiUser,
                instagramHesap: $hesap,
                instagramKisi: $mesaj->kisi,
                instagramMesaj: $mesaj,
            );
        }

        $sohbet = Sohbet::query()->with('eslesme')->find($sohbetId);
        if (!$sohbet || !$sohbet->eslesme) {
            return null;
        }

        $gelenMesaj = $gelenMesajId ? Mesaj::query()->find($gelenMesajId) : null;
        $hedefUserId = (int) $sohbet->eslesme->user_id === (int) $aiUser->id
            ? (int) $sohbet->eslesme->eslesen_user_id
            : (int) $sohbet->eslesme->user_id;
        $hedefUser = User::query()->find($hedefUserId);

        if (!$hedefUser) {
            return null;
        }

        return new AiTurnContext(
            kanal: 'dating',
            turnType: $turnType,
            aiUser: $aiUser,
            sohbet: $sohbet,
            gelenMesaj: $gelenMesaj,
            hedefUser: $hedefUser,
        );
    }
}
