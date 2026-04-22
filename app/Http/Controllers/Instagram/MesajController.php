<?php
namespace App\Http\Controllers\Instagram;

use App\Models\AiCevapBlokaj;
use App\Helpers\SohbetBitirenHelper;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instagram\MesajAlRequest;
use App\Http\Resources\InstagramMesajResource;
use App\Jobs\InstagramAiCevapGorevi;
use App\Models\InstagramAiGorevi;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\InstagramMesaj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MesajController extends Controller
{
    public function alVeKaydet(MesajAlRequest $request, InstagramHesap $hesap): JsonResponse
    {
        $this->yetkilendir($request, $hesap);

        $veri = $request->validated();
        $kaydedilenler = [];
        $tetiklenecekAiMesajlari = [];

        foreach ($veri['mesajlar'] as $mesajVerisi) {
            Log::debug('AI Kuyruğa alınacak mesaj adayı', [
                'mesajVerisi' => $mesajVerisi,
                'hesap_id' => $hesap->id,
            ]);
            $kisi = InstagramKisi::query()
                ->where('instagram_hesap_id', $hesap->id)
                ->where('instagram_kisi_id', $mesajVerisi['instagram_kisi_id'])
                ->first();

            if (!$kisi) {
                continue;
            }

            // 1. AI cevap blokajı kontrolü
            $blokaj = AiCevapBlokaj::where('instagram_hesap_id', $hesap->id)
                ->where('instagram_kisi_id', $kisi->id)
                ->where('blokaj_bitis', '>', now())
                ->first();
            if ($blokaj) {
                Log::debug('AI blokajı nedeniyle mesaj AI kuyruğuna alınmadı', [
                    'hesap_id' => $hesap->id,
                    'kisi_id' => $kisi->id,
                    'blokaj_bitis' => $blokaj->blokaj_bitis,
                ]);
                continue;
            }

            if (!empty($mesajVerisi['instagram_mesaj_kodu'])) {
                $mevcut = InstagramMesaj::query()
                    ->where('instagram_mesaj_kodu', $mesajVerisi['instagram_mesaj_kodu'])
                    ->exists();

                if ($mevcut) {
                    continue;
                }
            }

            $mesaj = DB::transaction(function () use ($hesap, $kisi, $mesajVerisi) {
                $mesaj = InstagramMesaj::create([
                    'instagram_hesap_id' => $hesap->id,
                    'instagram_kisi_id' => $kisi->id,
                    'gonderen_tipi' => $mesajVerisi['gonderen_tipi'],
                    'mesaj_metni' => $mesajVerisi['mesaj_metni'],
                    'mesaj_tipi' => $mesajVerisi['mesaj_tipi'] ?? 'metin',
                    'instagram_mesaj_kodu' => $mesajVerisi['instagram_mesaj_kodu'] ?? null,
                ]);

                if ($mesajVerisi['gonderen_tipi'] === 'karsi_taraf' && $hesap->otomatik_cevap_aktif_mi) {
                    $this->sonMesajHariciAiDurumunuTemizle($hesap, $kisi, $mesaj);
                    $this->goreviBekliyorOlarakHazirla($hesap, $kisi, $mesaj);
                    // 2. Sohbet bitiren mesaj ise blokaj ekle
                    if (isset($mesajVerisi['mesaj_metni']) && SohbetBitirenHelper::mesajSohbetBitirenMi($mesajVerisi['mesaj_metni'])) {
                        AiCevapBlokaj::updateOrCreate(
                            [
                                'instagram_hesap_id' => $hesap->id,
                                'instagram_kisi_id' => $kisi->id,
                            ],
                            [
                                'blokaj_bitis' => now()->addMinutes(30),
                            ]
                        );
                        Log::debug('Sohbet bitiren mesaj sonrası AI blokajı eklendi', [
                            'hesap_id' => $hesap->id,
                            'kisi_id' => $kisi->id,
                            'blokaj_bitis' => now()->addMinutes(30),
                        ]);
                    }
                }

                return $mesaj;
            });

            if ($mesajVerisi['gonderen_tipi'] === 'karsi_taraf' && $hesap->otomatik_cevap_aktif_mi) {
                Log::debug('AI Kuyruğa alınacak: tetiklenecekAiMesajlari', [
                    'mesaj_id' => $mesaj->id,
                    'kisi_id' => $kisi->id,
                    'hesap_id' => $hesap->id,
                ]);
                $tetiklenecekAiMesajlari[$kisi->id] = $mesaj;
            }

            $kaydedilenler[] = $mesaj;
        }

        foreach ($tetiklenecekAiMesajlari as $mesaj) {
            Log::debug('AI Job dispatch ediliyor', [
                'mesaj_id' => $mesaj->id,
                'hesap_id' => $hesap->id,
            ]);
            InstagramAiCevapGorevi::dispatch($mesaj, $hesap);
        }

        return response()->json([
            'kaydedilen_sayisi' => count($kaydedilenler),
        ], 201);
    }

    public function gidenKuyruk(Request $request, InstagramHesap $hesap): JsonResponse
    {
        $this->yetkilendir($request, $hesap);

        $sonBekleyenAiMesajlari = InstagramMesaj::query()
            ->selectRaw('MAX(id) as id')
            ->where('instagram_hesap_id', $hesap->id)
            ->where('gonderen_tipi', 'ai')
            ->where('gonderildi_mi', false)
            ->whereNotNull('mesaj_metni')
            ->where('mesaj_metni', '!=', '')
            ->groupBy('instagram_kisi_id');

        $bekleyenler = InstagramMesaj::query()
            ->whereIn('id', $sonBekleyenAiMesajlari)
            ->with('kisi:id,instagram_kisi_id,kullanici_adi,gorunen_ad')
            ->orderByDesc('id')
            ->get();

        return InstagramMesajResource::collection($bekleyenler)->response();
    }

    public function gonderildiIsaretle(Request $request, InstagramMesaj $mesaj): JsonResponse
    {
        Gate::authorize('isaretle', $mesaj);

        $mesaj->update([
            'gonderildi_mi' => true,
        ]);

        return response()->json([
            'mesaj' => 'Isaretlendi.',
        ]);
    }

    private function yetkilendir(Request $request, InstagramHesap $hesap): void
    {
        Gate::authorize('yonet', $hesap);
    }

    private function sonMesajHariciAiDurumunuTemizle(
        InstagramHesap $hesap,
        InstagramKisi $kisi,
        InstagramMesaj $aktifMesaj,
    ): void {
        $gecersizMesajIdleri = InstagramMesaj::query()
            ->where('instagram_hesap_id', $hesap->id)
            ->where('instagram_kisi_id', $kisi->id)
            ->where('gonderen_tipi', 'karsi_taraf')
            ->where('ai_cevapladi_mi', false)
            ->where('id', '<', $aktifMesaj->id)
            ->pluck('id');

        if ($gecersizMesajIdleri->isNotEmpty()) {
            InstagramMesaj::query()
                ->whereIn('id', $gecersizMesajIdleri)
                ->update([
                    'ai_cevapladi_mi' => true,
                ]);

            InstagramAiGorevi::query()
                ->whereIn('instagram_mesaj_id', $gecersizMesajIdleri)
                ->whereNotIn('durum', ['basarisiz', 'gecersiz'])
                ->update([
                    'durum' => 'gecersiz',
                    'hata_mesaji' => 'Daha yeni mesaj alindigi icin gecersizlesti.',
                    'updated_at' => now(),
                ]);
        }

        InstagramMesaj::query()
            ->where('instagram_hesap_id', $hesap->id)
            ->where('instagram_kisi_id', $kisi->id)
            ->where('gonderen_tipi', 'ai')
            ->where('gonderildi_mi', false)
            ->delete();
    }

    private function goreviBekliyorOlarakHazirla(
        InstagramHesap $hesap,
        InstagramKisi $kisi,
        InstagramMesaj $mesaj,
    ): void {
        InstagramAiGorevi::updateOrCreate(
            ['instagram_mesaj_id' => $mesaj->id],
            [
                'instagram_hesap_id' => $hesap->id,
                'instagram_kisi_id' => $kisi->id,
                'durum' => 'bekliyor',
                'deneme_sayisi' => 0,
                'hata_mesaji' => null,
                'cevap_metni' => null,
                // Sadece Gemini kullanılıyor, alanlar kaldırıldı
                'istek_baslatildi_at' => null,
                'son_parca_at' => null,
                'tamamlandi_at' => null,
                'yanit_suresi_ms' => null,
            ]
        );
    }
}
