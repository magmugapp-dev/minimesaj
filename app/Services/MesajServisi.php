<?php

namespace App\Services;

use App\Exceptions\MesajlasmaEngeliException;
use App\Events\MesajGonderildi;
use App\Events\MesajlarOkundu;
use App\Events\YapayZekaCevabiHazir;
use App\Jobs\ProcessAiTurnJob;
use App\Jobs\YapayZekaCevapGorevi;
use App\Models\Engelleme;
use App\Models\Mesaj;
use App\Models\SessizeAlinanKullanici;
use App\Models\Sohbet;
use App\Models\User;
use App\Models\YapayZekaGorevi;
use App\Notifications\YeniMesaj;
use App\Services\YapayZeka\AiKullaniciHazirlamaServisi;
use App\Services\YapayZeka\AiMesajZamanlamaServisi;
use App\Services\YapayZeka\V2\AiPersonaService;
use App\Support\Language;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;

class MesajServisi
{
    public function __construct(
        private ?AiKullaniciHazirlamaServisi $aiKullaniciHazirlamaServisi = null,
        private ?AiPersonaService $aiPersonaService = null,
        private ?AiMesajZamanlamaServisi $aiMesajZamanlamaServisi = null,
    ) {
        $this->aiKullaniciHazirlamaServisi ??= app(AiKullaniciHazirlamaServisi::class);
        $this->aiPersonaService ??= app(AiPersonaService::class);
        $this->aiMesajZamanlamaServisi ??= app(AiMesajZamanlamaServisi::class);
    }

    public function gonder(Sohbet $sohbet, User $gonderen, array $veri): Mesaj
    {
        return DB::transaction(function () use ($sohbet, $gonderen, $veri) {
            $eslesme = $sohbet->eslesme;
            $karsiTarafId = $eslesme->user_id === $gonderen->id
                ? $eslesme->eslesen_user_id
                : $eslesme->user_id;

            if ($this->gonderenKarsiTarafTarafindanEngellenmis((int) $gonderen->id, (int) $karsiTarafId)) {
                throw new MesajlasmaEngeliException();
            }

            $karsiTaraf = User::find($karsiTarafId);
            $language = $this->messageLanguageFor($gonderen);

            $mesaj = Mesaj::create([
                'sohbet_id' => $sohbet->id,
                'gonderen_user_id' => $gonderen->id,
                'mesaj_tipi' => $veri['mesaj_tipi'] ?? 'metin',
                'mesaj_metni' => $veri['mesaj_metni'] ?? null,
                'dil_kodu' => $language['code'],
                'dil_adi' => $language['name'],
                'dosya_yolu' => $veri['dosya_yolu'] ?? null,
                'dosya_suresi' => $veri['dosya_suresi'] ?? null,
                'dosya_boyutu' => $veri['dosya_boyutu'] ?? null,
                'cevaplanan_mesaj_id' => $veri['cevaplanan_mesaj_id'] ?? null,
            ]);

            $sohbet->update([
                'son_mesaj_id' => $mesaj->id,
                'son_mesaj_tarihi' => $mesaj->created_at,
                'toplam_mesaj_sayisi' => DB::raw('toplam_mesaj_sayisi + 1'),
            ]);

            if ($karsiTaraf?->hesap_tipi === 'ai') {
                $this->aiKullaniciHazirlamaServisi->hazirla($karsiTaraf);
            }

            DB::afterCommit(function () use ($mesaj, $sohbet, $gonderen, $karsiTaraf) {
                MesajGonderildi::dispatch($mesaj);

                if ($karsiTaraf?->hesap_tipi === 'ai') {
                    $aiUser = $karsiTaraf->fresh('aiAyar') ?? $karsiTaraf;
                    $zamanlama = $this->aiMesajZamanlamaServisi->sohbetCevabiDurumu(
                        $mesaj,
                        $aiUser,
                        now(),
                    );

                    YapayZekaGorevi::updateOrCreate(
                        [
                            'gelen_mesaj_id' => $mesaj->id,
                            'ai_user_id' => $aiUser->id,
                        ],
                        [
                            'sohbet_id' => $sohbet->id,
                            'durum' => $this->bekleyenGorevDurumu($zamanlama['bekleme_nedeni'] ?? null),
                            'deneme_sayisi' => 0,
                            'hata_mesaji' => null,
                            'cevap_metni' => null,
                            'saglayici_tipi' => $aiUser->aiAyar?->saglayici_tipi ?? 'gemini',
                            'model_adi' => $aiUser->aiAyar?->model_adi ?? 'gemini-2.5-flash',
                            'istek_baslatildi_at' => null,
                            'son_parca_at' => null,
                            'tamamlandi_at' => null,
                            'yanit_suresi_ms' => null,
                        ]
                    );

                    if (app()->environment('testing')) {
                        if ($this->queueIsFaked()) {
                            YapayZekaCevapGorevi::dispatch($sohbet, $mesaj, $aiUser);
                        }

                        return;
                    }

                    if (app()->environment('local')) {
                        ProcessAiTurnJob::dispatchSync(
                            'dating',
                            'reply',
                            $aiUser->id,
                            $sohbet->id,
                            $mesaj->id,
                        );

                        return;
                    }

                    ProcessAiTurnJob::dispatch(
                        'dating',
                        'reply',
                        $aiUser->id,
                        $sohbet->id,
                        $mesaj->id,
                    );

                    return;
                }

                if (
                    $karsiTaraf instanceof User
                    && ! SessizeAlinanKullanici::aktifKayitVarMi((int) $karsiTaraf->id, (int) $gonderen->id)
                ) {
                    $karsiTaraf->notify(new YeniMesaj($mesaj, $gonderen));
                }
            });

            return $mesaj;
        });
    }

    public function gonderAiMesaji(Sohbet $sohbet, User $aiUser, array $veri): Mesaj
    {
        return DB::transaction(function () use ($sohbet, $aiUser, $veri) {
            $language = $this->messageLanguageFor($aiUser);

            $mesaj = Mesaj::create([
                'sohbet_id' => $sohbet->id,
                'gonderen_user_id' => $aiUser->id,
                'mesaj_tipi' => $veri['mesaj_tipi'] ?? 'metin',
                'mesaj_metni' => $veri['mesaj_metni'] ?? null,
                'dil_kodu' => $veri['dil_kodu'] ?? $language['code'],
                'dil_adi' => $veri['dil_adi'] ?? $language['name'],
                'dosya_yolu' => $veri['dosya_yolu'] ?? null,
                'dosya_suresi' => $veri['dosya_suresi'] ?? null,
                'dosya_boyutu' => $veri['dosya_boyutu'] ?? null,
                'cevaplanan_mesaj_id' => $veri['cevaplanan_mesaj_id'] ?? null,
                'ai_tarafindan_uretildi_mi' => true,
            ]);

            $sohbet->update([
                'son_mesaj_id' => $mesaj->id,
                'son_mesaj_tarihi' => $mesaj->created_at,
                'toplam_mesaj_sayisi' => DB::raw('toplam_mesaj_sayisi + 1'),
            ]);

            $eslesme = $sohbet->eslesme;
            $karsiTarafId = $eslesme->user_id === $aiUser->id
                ? $eslesme->eslesen_user_id
                : $eslesme->user_id;

            $karsiTaraf = User::find($karsiTarafId);

            DB::afterCommit(function () use ($mesaj, $aiUser, $karsiTaraf) {
                YapayZekaCevabiHazir::dispatch($mesaj);

                if (
                    $karsiTaraf instanceof User
                    && ! SessizeAlinanKullanici::aktifKayitVarMi((int) $karsiTaraf->id, (int) $aiUser->id)
                ) {
                    $karsiTaraf->notify(new YeniMesaj($mesaj, $aiUser));
                }
            });

            return $mesaj;
        });
    }

    public function okuduIsaretle(Sohbet $sohbet, User $okuyan): int
    {
        $eslesme = $sohbet->eslesme;
        $karsiTarafId = $eslesme->user_id === $okuyan->id
            ? $eslesme->eslesen_user_id
            : $eslesme->user_id;

        $guncellenen = Mesaj::where('sohbet_id', $sohbet->id)
            ->where('gonderen_user_id', $karsiTarafId)
            ->where('okundu_mu', false)
            ->update(['okundu_mu' => true]);

        if ($guncellenen > 0) {
            MesajlarOkundu::dispatch($sohbet, $okuyan->id, $guncellenen);
        }

        return $guncellenen;
    }

    private function bekleyenGorevDurumu(?string $beklemeNedeni): string
    {
        return match ($beklemeNedeni) {
            'aktif_saat_disinda' => 'aktif_saat_bekleniyor',
            'cevrim_disi' => 'cevrim_disi_bekleniyor',
            'sohbet_sessizde' => 'sessiz_mod_bekleniyor',
            'cevap_akisi' => 'cevap_akisi_bekleniyor',
            'ai_pasif' => 'ai_pasif',
            default => 'bekliyor',
        };
    }

    private function gonderenKarsiTarafTarafindanEngellenmis(int $gonderenId, int $karsiTarafId): bool
    {
        return Engelleme::query()
            ->where('engelleyen_user_id', $karsiTarafId)
            ->where('engellenen_user_id', $gonderenId)
            ->exists();
    }

    private function messageLanguageFor(User $user): array
    {
        if ($user->hesap_tipi === 'ai') {
            $persona = $this->aiPersonaService->ensureForUser($user);
            $code = Language::normalizeCode($persona->ana_dil_kodu) ?: Language::normalizeCode($user->dil) ?: 'tr';
            return [
                'code' => $code,
                'name' => $persona->ana_dil_adi ?: Language::name($code),
            ];
        }

        $code = Language::normalizeCode($user->dil);

        return [
            'code' => $code,
            'name' => Language::name($code),
        ];
    }

    private function queueIsFaked(): bool
    {
        return Queue::getFacadeRoot() instanceof QueueFake;
    }
}
