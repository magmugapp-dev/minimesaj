<?php

namespace App\Services\YapayZeka\V2\Channels;

use App\Models\User;
use App\Services\YapayZeka\V2\Data\AiTurnContext;

interface AiChannelAdapterInterface
{
    public function recentMessages(AiTurnContext $context, int $limit = 12): array;

    public function counterpartProfileLines(AiTurnContext $context): array;

    public function hasNewerIncoming(AiTurnContext $context): bool;

    public function persistReply(AiTurnContext $context, User $aiUser, string $replyText): mixed;

    public function markIncomingHandled(AiTurnContext $context): void;
}
