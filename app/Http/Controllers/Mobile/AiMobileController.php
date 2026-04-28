<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\MesajResource;
use App\Models\AiMessageTurn;
use App\Models\AiPromptVersion;
use App\Models\Ayar;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\Ai\AiTurnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiMobileController extends Controller
{
    public function __construct(private AiTurnService $turnService) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $prompt = AiPromptVersion::query()->where('active', true)->latest('id')->first();

        return response()->json([
            'prompt' => $prompt ? [
                'version' => $prompt->version,
                'hash' => $prompt->hash,
                'prompt_xml' => $prompt->prompt_xml,
            ] : null,
            'pending_turn_count' => $this->turnService->pendingTurnsFor($request->user())->count(),
            'retry' => [
                'max_attempts' => 5,
                'defer_minutes' => 5,
                'silent' => true,
            ],
        ]);
    }

    public function pendingTurns(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->turnService->pendingTurnsFor($request->user())
                ->map(fn (AiMessageTurn $turn) => $this->turnPayload($turn))
                ->values(),
        ]);
    }

    public function turnContext(Request $request, Sohbet $conversation): JsonResponse
    {
        $validated = $request->validate([
            'turn_id' => ['required', 'integer', 'exists:ai_message_turns,id'],
        ]);

        $turn = AiMessageTurn::query()
            ->with('aiUser')
            ->where('conversation_id', $conversation->id)
            ->findOrFail($validated['turn_id']);

        return response()->json($this->turnService->contextForTurn($turn, $request->user()));
    }

    public function geminiStream(Request $request): mixed
    {
        $validated = $request->validate([
            'turn_id' => ['nullable', 'integer', 'exists:ai_message_turns,id'],
            'translation' => ['nullable', 'boolean'],
            'model' => ['required', 'string', 'max:80'],
            'payload' => ['required', 'array'],
        ]);

        $turn = isset($validated['turn_id'])
            ? AiMessageTurn::query()->findOrFail($validated['turn_id'])
            : null;

        if ($turn) {
            abort_unless($this->turnBelongsToUser($turn, $request->user()), 403);
            abort_unless(in_array($turn->status, [AiMessageTurn::STATUS_PENDING, AiMessageTurn::STATUS_DEFERRED, AiMessageTurn::STATUS_PROCESSING], true), 409);
            $turn->forceFill([
                'status' => AiMessageTurn::STATUS_PROCESSING,
                'started_at' => now(),
                'last_error' => null,
            ])->save();
        } elseif (!($validated['translation'] ?? false)) {
            abort(422, 'Turn id required.');
        }

        $apiKey = trim((string) Ayar::query()->where('anahtar', 'gemini_api_key')->value('deger'));
        abort_if($apiKey === '', 500, 'Gemini API key missing.');

        $model = $this->safeModel((string) $validated['model']);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'text/event-stream',
        ])
            ->withOptions(['stream' => true, 'connect_timeout' => 6, 'read_timeout' => 45])
            ->timeout(45)
            ->send('POST', $url, ['json' => $validated['payload']]);

        if ($response->status() >= 400) {
            if ($turn && in_array($response->status(), [429, 500, 503], true)) {
                $this->turnService->markRetryableFailure($turn, 'Gemini '.$response->status());
            }

            return response()->json([
                'message' => 'Gemini temporarily unavailable.',
                'retryable' => in_array($response->status(), [429, 500, 503], true),
            ], $response->status());
        }

        return response()->stream(function () use ($response): void {
            $body = $response->toPsrResponse()->getBody();
            while (!$body->eof()) {
                echo $body->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function reply(Request $request, Sohbet $conversation): JsonResponse
    {
        $validated = $request->validate([
            'turn_id' => ['required', 'integer', 'exists:ai_message_turns,id'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*' => ['required', 'string', 'max:5000'],
            'client_message_id' => ['nullable', 'string', 'max:96'],
        ]);

        $turn = AiMessageTurn::query()
            ->with('aiUser')
            ->where('conversation_id', $conversation->id)
            ->findOrFail($validated['turn_id']);

        $messages = $this->turnService->persistReply(
            $turn,
            $request->user(),
            $validated['parts'],
            $validated['client_message_id'] ?? null,
        );

        return response()->json([
            'data' => MesajResource::collection(collect($messages))->resolve($request),
        ]);
    }

    public function violation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_user_id' => ['required', 'integer', 'exists:users,id'],
            'category' => ['required', 'string', 'max:64'],
        ]);

        $aiUser = User::query()
            ->where('hesap_tipi', 'ai')
            ->findOrFail($validated['ai_user_id']);

        return response()->json($this->turnService->recordViolation(
            $request->user(),
            $aiUser,
            $validated['category'],
        ));
    }

    private function turnPayload(AiMessageTurn $turn): array
    {
        return [
            'id' => $turn->id,
            'conversation_id' => $turn->conversation_id,
            'source_message_id' => $turn->source_message_id,
            'ai_user_id' => $turn->ai_user_id,
            'status' => $turn->status,
            'planned_at' => $turn->planned_at?->toISOString(),
            'retry_after' => $turn->retry_after?->toISOString(),
            'attempt_count' => $turn->attempt_count,
            'max_attempts' => $turn->max_attempts,
        ];
    }

    private function turnBelongsToUser(AiMessageTurn $turn, User $user): bool
    {
        return $turn->conversation()
            ->whereHas('eslesme', function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhere('eslesen_user_id', $user->id);
            })
            ->exists();
    }

    private function safeModel(string $model): string
    {
        $model = trim($model);
        abort_unless(Str::startsWith($model, 'gemini-'), 422, 'Invalid model.');

        return $model;
    }
}
