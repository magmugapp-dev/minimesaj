<?php

namespace App\Services\YapayZeka\V2\Channels;

use App\Models\Mesaj;
use App\Models\User;
use App\Services\MesajServisi;
use App\Services\YapayZeka\V2\Data\AiTurnContext;

class DatingChannelAdapter implements AiChannelAdapterInterface
{
    public function __construct(private ?MesajServisi $mesajServisi = null)
    {
        $this->mesajServisi ??= app(MesajServisi::class);
    }

    public function recentMessages(AiTurnContext $context, int $limit = 12): array
    {
        if (!$context->sohbet) {
            return [];
        }

        return $context->sohbet->mesajlar()
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (Mesaj $mesaj) => [
                'role' => (int) $mesaj->gonderen_user_id === (int) $context->aiUser->id ? 'assistant' : 'user',
                'content' => $mesaj->mesaj_metni ?: '[medya]',
            ])
            ->values()
            ->all();
    }

    public function counterpartProfileLines(AiTurnContext $context): array
    {
        $user = $context->hedefUser;

        return [
            'Ad: ' . ($user?->ad ?: 'Bilinmiyor'),
            'Kullanici adi: ' . ($user?->kullanici_adi ?: 'Bilinmiyor'),
            'Biyografi: ' . ($user?->biyografi ?: 'Belirtilmemis'),
            'Sehir: ' . ($user?->il ?: 'Belirtilmemis'),
            'Ulke: ' . ($user?->ulke ?: 'Belirtilmemis'),
        ];
    }

    public function hasNewerIncoming(AiTurnContext $context): bool
    {
        if (!$context->sohbet || !$context->gelenMesaj) {
            return false;
        }

        return Mesaj::query()
            ->where('sohbet_id', $context->sohbet->id)
            ->where('gonderen_user_id', '!=', $context->aiUser->id)
            ->where('id', '>', $context->gelenMesaj->id)
            ->exists();
    }

    public function persistReply(AiTurnContext $context, User $aiUser, string $replyText): mixed
    {
        return $this->mesajServisi->gonderAiMesaji($context->sohbet, $aiUser, [
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => $replyText,
        ]);
    }

    public function markIncomingHandled(AiTurnContext $context): void
    {
        // Dating tarafinda ayri handled isareti tutulmuyor.
    }
}
