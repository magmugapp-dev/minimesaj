<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Odeme\HediyeGonderRequest;
use App\Http\Resources\HediyeGonderimiResource;
use App\Http\Resources\HediyeResource;
use App\Models\Hediye;
use App\Models\HediyeGonderimi;
use App\Models\SessizeAlinanKullanici;
use App\Models\User;
use App\Notifications\HediyeAlindi;
use App\Services\PuanServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HediyeController extends Controller
{
    public function __construct(private PuanServisi $puanServisi) {}

    public function index(): AnonymousResourceCollection
    {
        return HediyeResource::collection(
            Hediye::query()
                ->aktif()
                ->orderBy('sira')
                ->orderBy('id')
                ->get()
        );
    }

    public function gonder(HediyeGonderRequest $request): JsonResponse
    {
        $veri = $request->validated();
        $gonderen = $request->user();

        if ($gonderen->id === (int) $veri['alici_user_id']) {
            return response()->json(['mesaj' => 'Kendinize hediye gonderemezsiniz.'], 422);
        }

        $hediye = $this->hediyeBul($veri);
        if (!$hediye) {
            return response()->json(['mesaj' => 'Hediye bulunamadi veya aktif degil.'], 422);
        }

        try {
            $this->puanServisi->harca(
                $gonderen,
                (int) $hediye->puan_bedeli,
                "Hediye gonderildi: {$hediye->ad}",
                'hediye_gonderimi',
                null,
            );
        } catch (\DomainException $e) {
            return response()->json(['mesaj' => $e->getMessage()], 422);
        }

        $gonderim = HediyeGonderimi::create([
            'gonderen_user_id' => $gonderen->id,
            'alici_user_id' => $veri['alici_user_id'],
            'hediye_id' => $hediye->id,
            'hediye_adi' => $hediye->ad,
            'puan_bedeli' => $hediye->puan_bedeli,
        ]);

        $alici = User::find($veri['alici_user_id']);
        if (
            $alici instanceof User
            && ! SessizeAlinanKullanici::aktifKayitVarMi((int) $alici->id, (int) $gonderen->id)
        ) {
            $alici->notify(new HediyeAlindi($gonderim, $gonderen));
        }

        return response()->json([
            'mesaj' => 'Hediye gonderildi!',
            'gonderim' => new HediyeGonderimiResource($gonderim->load(['hediye', 'gonderen'])),
            'mevcut_puan' => (int) $gonderen->fresh()->mevcut_puan,
        ], 201);
    }

    private function hediyeBul(array $veri): ?Hediye
    {
        if (!empty($veri['hediye_id'])) {
            return Hediye::query()
                ->aktif()
                ->find((int) $veri['hediye_id']);
        }

        return Hediye::query()
            ->aktif()
            ->where(function ($query) use ($veri) {
                $deger = (string) ($veri['hediye_tipi'] ?? '');

                $query->where('ad', $deger)
                    ->orWhere('kod', $deger);
            })
            ->first();
    }
}
