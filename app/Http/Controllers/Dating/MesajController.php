<?php

namespace App\Http\Controllers\Dating;

use App\Exceptions\MesajlasmaEngeliException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\MesajGonderRequest;
use App\Http\Resources\MesajResource;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Services\MesajServisi;
use App\Services\SohbetTypingService;
use App\Services\YapayZeka\V2\AiTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MesajController extends Controller
{
    public function __construct(
        private MesajServisi $mesajServisi,
        private AiTranslationService $translationService,
        private SohbetTypingService $typingService,
    ) {}

    public function listele(Request $request, Sohbet $sohbet)
    {
        $this->yetkilendir($request, $sohbet);

        $mesajlar = $sohbet->mesajlar()
            ->with('gonderen:id,ad,kullanici_adi,profil_resmi,dil')
            ->latest()
            ->paginate(50);

        return MesajResource::collection($mesajlar)->additional([
            'ai' => [
                'status' => $sohbet->ai_durumu,
                'status_text' => $this->normalizeAiStatusText($sohbet->ai_durum_metni, $sohbet->ai_durumu),
                'planned_at' => $sohbet->ai_planlanan_cevap_at?->toISOString(),
            ],
        ]);
    }

    public function gonder(MesajGonderRequest $request, Sohbet $sohbet): JsonResponse
    {
        $this->yetkilendir($request, $sohbet);

        $veri = $request->validated();

        try {
            $mesaj = $this->mesajServisi->gonder($sohbet, $request->user(), $veri);
        } catch (MesajlasmaEngeliException $exception) {
            return response()->json([
                'durum' => 'engellendi',
                'kod' => 'engellendi',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $this->typingService->setTyping($sohbet, $request->user(), false);

        return (new MesajResource($mesaj->load('gonderen:id,ad,kullanici_adi,profil_resmi,dil')))
            ->response()
            ->setStatusCode(201);
    }

    public function cevir(Request $request, Sohbet $sohbet, Mesaj $mesaj): JsonResponse
    {
        $this->yetkilendir($request, $sohbet);

        abort_unless((int) $mesaj->sohbet_id === (int) $sohbet->id, 404);

        if ((int) $mesaj->gonderen_user_id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'Sadece gelen mesajlar cevrilebilir.',
            ], 422);
        }

        if ($mesaj->mesaj_tipi !== 'metin' || trim((string) $mesaj->mesaj_metni) === '') {
            return response()->json([
                'message' => 'Sadece text mesajlar cevrilebilir.',
            ], 422);
        }

        $ceviri = $this->translationService->translateIncomingMessage($mesaj, $request->user());

        return response()->json([
            'ceviri' => $ceviri,
            'ceviri_metni' => $ceviri['metin'] ?? '',
        ]);
    }

    public function typing(Request $request, Sohbet $sohbet): JsonResponse
    {
        $this->yetkilendir($request, $sohbet);

        $validated = $request->validate([
            'typing' => ['required', 'boolean'],
        ]);

        $typing = (bool) $validated['typing'];
        $this->typingService->setTyping($sohbet, $request->user(), $typing);

        return response()->json([
            'ok' => true,
            'typing' => $typing,
        ]);
    }

    public function okuduIsaretle(Request $request, Sohbet $sohbet): JsonResponse
    {
        $this->yetkilendir($request, $sohbet);

        $okunan = $this->mesajServisi->okuduIsaretle($sohbet, $request->user());

        return response()->json(['okunan_mesaj_sayisi' => $okunan]);
    }

    private function yetkilendir(Request $request, Sohbet $sohbet): void
    {
        Gate::authorize('erisebilir', $sohbet);
    }

    private function normalizeAiStatusText(?string $statusText, ?string $status): ?string
    {
        $normalized = trim((string) $statusText);

        if ($normalized === 'Dusunuyor...') {
            return 'Yaziyor...';
        }

        if ($normalized !== '') {
            return $normalized;
        }

        return in_array($status, ['queued', 'typing'], true) ? 'Yaziyor...' : null;
    }
}
