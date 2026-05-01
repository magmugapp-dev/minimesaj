<?php

namespace App\Console\Commands;

use App\Models\AiCharacter;
use App\Models\AiMessageTurn;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\Users\UserOnlineStatusService;
use Illuminate\Console\Command;

class ScheduleProactiveAiMessages extends Command
{
    protected $signature = 'ai:proaktif-mesajlari-planla {--limit=500}';

    protected $description = 'Uygun sohbetler icin proaktif AI mesaj turnleri olusturur.';

    public function handle(UserOnlineStatusService $onlineStatusService): int
    {
        $created = 0;
        $limit = max(1, (int) $this->option('limit'));

        AiCharacter::query()
            ->where('active', true)
            ->where('reengagement_active', true)
            ->with('user')
            ->chunkById(100, function ($characters) use (&$created, $limit, $onlineStatusService): bool {
                foreach ($characters as $character) {
                    if ($created >= $limit) {
                        return false;
                    }

                    $aiUser = $character->user;
                    if (!$aiUser instanceof User || $aiUser->hesap_durumu !== 'aktif') {
                        continue;
                    }

                    if (!($onlineStatusService->resolve($aiUser, now(), false)['is_online'] ?? false)) {
                        continue;
                    }

                    $dailyLimit = max(1, (int) $character->reengagement_daily_limit);
                    $createdToday = AiMessageTurn::query()
                        ->where('ai_user_id', $aiUser->id)
                        ->where('turn_type', 'proactive')
                        ->whereDate('created_at', today())
                        ->count();
                    if ($createdToday >= $dailyLimit) {
                        continue;
                    }

                    foreach ($this->candidateConversations($aiUser, $character) as $conversation) {
                        if ($created >= $limit || $createdToday >= $dailyLimit) {
                            break;
                        }

                        if (!$this->canCreateTurn($conversation, $aiUser, $character)) {
                            continue;
                        }

                        $turn = AiMessageTurn::query()->firstOrCreate([
                            'idempotency_key' => 'proactive:'.$conversation->id.':'.$aiUser->id.':'.now()->format('YmdH'),
                        ], [
                            'conversation_id' => $conversation->id,
                            'ai_user_id' => $aiUser->id,
                            'source_message_id' => null,
                            'turn_type' => 'proactive',
                            'status' => AiMessageTurn::STATUS_PENDING,
                            'planned_at' => now(),
                            'attempt_count' => 0,
                            'max_attempts' => 5,
                        ]);

                        if ($turn->wasRecentlyCreated) {
                            $created++;
                            $createdToday++;
                            $character->forceFill(['last_reengagement_at' => now()])->save();
                        }
                    }
                }

                return true;
            });

        $this->info("{$created} proaktif AI turn olusturuldu.");

        return self::SUCCESS;
    }

    private function candidateConversations(User $aiUser, AiCharacter $character)
    {
        $threshold = now()->subHours(max(1, (int) $character->reengagement_after_hours));

        return Sohbet::query()
            ->where('durum', 'aktif')
            ->where(function ($query): void {
                $query->whereNull('ai_ghost_lockout_until')
                    ->orWhere('ai_ghost_lockout_until', '<=', now());
            })
            ->whereHas('eslesme', function ($query) use ($aiUser): void {
                $query->where('durum', 'aktif')
                    ->where(function ($inner) use ($aiUser): void {
                        $inner->where('user_id', $aiUser->id)
                            ->orWhere('eslesen_user_id', $aiUser->id);
                    });
            })
            ->where('son_mesaj_tarihi', '<=', $threshold)
            ->with('eslesme.user', 'eslesme.eslesenUser')
            ->orderBy('son_mesaj_tarihi')
            ->limit(50)
            ->get();
    }

    private function canCreateTurn(Sohbet $conversation, User $aiUser, AiCharacter $character): bool
    {
        if ($conversation->aiMessageTurns()
            ->whereIn('status', [AiMessageTurn::STATUS_PENDING, AiMessageTurn::STATUS_PROCESSING])
            ->exists()) {
            return false;
        }

        $peer = (int) $conversation->eslesme->user_id === (int) $aiUser->id
            ? $conversation->eslesme->eslesenUser
            : $conversation->eslesme->user;
        if (!$peer instanceof User || !$this->orientationAllows($character, $aiUser, $peer)) {
            return false;
        }

        $lastMessage = Mesaj::query()
            ->where('sohbet_id', $conversation->id)
            ->latest('id')
            ->first();
        if (!$lastMessage || (int) $lastMessage->gonderen_user_id === (int) $aiUser->id) {
            $doubleGap = now()->subHours(max(1, (int) $character->reengagement_after_hours) * 2);

            return $lastMessage
                && $lastMessage->created_at?->lessThanOrEqualTo($doubleGap)
                && $this->aiMessagesSinceLastUserMessage($conversation, $aiUser) === 1;
        }

        return true;
    }

    private function aiMessagesSinceLastUserMessage(Sohbet $conversation, User $aiUser): int
    {
        $lastUserMessageId = Mesaj::query()
            ->where('sohbet_id', $conversation->id)
            ->where('gonderen_user_id', '!=', $aiUser->id)
            ->latest('id')
            ->value('id');

        return Mesaj::query()
            ->where('sohbet_id', $conversation->id)
            ->where('gonderen_user_id', $aiUser->id)
            ->when($lastUserMessageId, fn ($query, $messageId) => $query->where('id', '>', $messageId))
            ->count();
    }

    private function orientationAllows(AiCharacter $character, User $aiUser, User $peer): bool
    {
        $orientation = strtolower((string) data_get($character->character_json, 'orientation', 'bisexual'));
        $aiGender = $aiUser->cinsiyet;
        $peerGender = $peer->cinsiyet;

        return match ($orientation) {
            'heterosexual', 'hetero' => ($aiGender === 'erkek' && $peerGender === 'kadin')
                || ($aiGender === 'kadin' && $peerGender === 'erkek'),
            'gay' => $aiGender === 'erkek' && $peerGender === 'erkek',
            'lesbian', 'lezbiyen' => $aiGender === 'kadin' && $peerGender === 'kadin',
            default => in_array($peerGender, ['erkek', 'kadin'], true),
        };
    }
}
