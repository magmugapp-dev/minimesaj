<?php

namespace App\Services\YapayZeka\V2\Channels;

use App\Events\InstagramAiCevapHazir;
use App\Models\InstagramMesaj;
use App\Models\User;
use App\Services\YapayZeka\V2\Data\AiTurnContext;

class InstagramChannelAdapter implements AiChannelAdapterInterface
{
    public function recentMessages(AiTurnContext $context, int $limit = 12): array
    {
        if (!$context->instagramHesap || !$context->instagramKisi) {
            return [];
        }

        return InstagramMesaj::query()
            ->where('instagram_hesap_id', $context->instagramHesap->id)
            ->where('instagram_kisi_id', $context->instagramKisi->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (InstagramMesaj $mesaj) => [
                'role' => $mesaj->gonderen_tipi === 'karsi_taraf' ? 'user' : 'assistant',
                'content' => $mesaj->mesaj_metni ?: '[medya]',
            ])
            ->values()
            ->all();
    }

    public function counterpartProfileLines(AiTurnContext $context): array
    {
        $person = $context->instagramKisi;

        return [
            'Gorunen ad: ' . ($person?->gorunen_ad ?: 'Bilinmiyor'),
            'Kullanici adi: ' . ($person?->kullanici_adi ?: 'Bilinmiyor'),
            'Instagram kisi id: ' . ($person?->instagram_kisi_id ?: 'Bilinmiyor'),
        ];
    }

    public function hasNewerIncoming(AiTurnContext $context): bool
    {
        if (!$context->instagramMesaj || !$context->instagramHesap || !$context->instagramKisi) {
            return false;
        }

        return InstagramMesaj::query()
            ->where('instagram_hesap_id', $context->instagramHesap->id)
            ->where('instagram_kisi_id', $context->instagramKisi->id)
            ->where('gonderen_tipi', 'karsi_taraf')
            ->where('id', '>', $context->instagramMesaj->id)
            ->exists();
    }

    public function persistReply(AiTurnContext $context, User $aiUser, string $replyText): mixed
    {
        $message = InstagramMesaj::query()->create([
            'instagram_hesap_id' => $context->instagramHesap->id,
            'instagram_kisi_id' => $context->instagramKisi->id,
            'gonderen_tipi' => 'ai',
            'mesaj_metni' => $replyText,
            'mesaj_tipi' => 'metin',
            'gonderildi_mi' => false,
        ]);

        InstagramAiCevapHazir::dispatch($message);

        return $message;
    }

    public function markIncomingHandled(AiTurnContext $context): void
    {
        if ($context->instagramMesaj) {
            $context->instagramMesaj->forceFill(['ai_cevapladi_mi' => true])->save();
        }
    }
}
