<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Odeme\HediyeGonderRequest;
use App\Models\HediyeGonderimi;
use App\Models\User;
use App\Notifications\HediyeAlindi;
use App\Services\PuanServisi;
use Illuminate\Http\JsonResponse;

class HediyeController extends Controller
{
    public function __construct(private PuanServisi $puanServisi) {}

    public function gonder(HediyeGonderRequest $request): JsonResponse
    {
        $veri = $request->validated();

        $gonderen = $request->user();

        if ($gonderen->id === (int) $veri['alici_user_id']) {
            return response()->json(['mesaj' => 'Kendinize hediye gönderemezsiniz.'], 422);
        }

        // Puan düşür
        try {
            $this->puanServisi->harca(
                $gonderen,
                $veri['puan_degeri'],
                "Hediye gönderildi: {$veri['hediye_tipi']}",
                'hediye_gonderimi',
                null,
            );
        } catch (\DomainException $e) {
            return response()->json(['mesaj' => $e->getMessage()], 422);
        }

        $gonderim = HediyeGonderimi::create([
            'gonderen_user_id' => $gonderen->id,
            'alici_user_id' => $veri['alici_user_id'],
            'hediye_adi' => $veri['hediye_tipi'],
            'puan_bedeli' => $veri['puan_degeri'],
        ]);

        $alici = User::find($veri['alici_user_id']);
        if ($alici instanceof User) {
            $alici->notify(new HediyeAlindi($gonderim, $gonderen));
        }

        return response()->json(['mesaj' => 'Hediye gönderildi!', 'gonderim' => $gonderim], 201);
    }
}
