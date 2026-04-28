<?php

namespace App\Services;

use App\Events\MesajGonderildi;
use App\Events\MesajlarOkundu;
use App\Exceptions\MesajlasmaEngeliException;
use App\Models\Engelleme;
use App\Models\Mesaj;
use App\Models\SessizeAlinanKullanici;
use App\Models\Sohbet;
use App\Models\User;
use App\Notifications\YeniMesaj;
use App\Services\Ai\AiTurnService;
use App\Support\AiMessageTextSanitizer;
use App\Support\Language;
use Illuminate\Support\Facades\DB;

class MesajServisi
{
    public function __construct(private ?AiTurnService $aiTurnService = null)
    {
        $this->aiTurnService ??= app(AiTurnService::class);
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
            $clientMessageId = trim((string) ($veri['client_message_id'] ?? ''));

            if ($clientMessageId !== '') {
                $mevcutMesaj = Mesaj::query()
                    ->where('sohbet_id', $sohbet->id)
                    ->where('gonderen_user_id', $gonderen->id)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($mevcutMesaj) {
                    return $mevcutMesaj;
                }
            }

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
                'client_message_id' => $clientMessageId !== '' ? $clientMessageId : null,
            ]);

            $sohbet->update([
                'son_mesaj_id' => $mesaj->id,
                'son_mesaj_tarihi' => $mesaj->created_at,
                'toplam_mesaj_sayisi' => DB::raw('toplam_mesaj_sayisi + 1'),
            ]);

            DB::afterCommit(function () use ($mesaj, $sohbet, $gonderen, $karsiTaraf) {
                MesajGonderildi::dispatch($mesaj);

                if ($karsiTaraf?->hesap_tipi === 'ai') {
                    $this->aiTurnService->createReplyTurn($sohbet, $mesaj, $karsiTaraf);
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
            $messageText = AiMessageTextSanitizer::sanitize($veri['mesaj_metni'] ?? null);

            $mesaj = Mesaj::create([
                'sohbet_id' => $sohbet->id,
                'gonderen_user_id' => $aiUser->id,
                'mesaj_tipi' => $veri['mesaj_tipi'] ?? 'metin',
                'mesaj_metni' => $messageText,
                'dil_kodu' => $veri['dil_kodu'] ?? $language['code'],
                'dil_adi' => $veri['dil_adi'] ?? $language['name'],
                'dosya_yolu' => $veri['dosya_yolu'] ?? null,
                'dosya_suresi' => $veri['dosya_suresi'] ?? null,
                'dosya_boyutu' => $veri['dosya_boyutu'] ?? null,
                'cevaplanan_mesaj_id' => $veri['cevaplanan_mesaj_id'] ?? null,
                'client_message_id' => $veri['client_message_id'] ?? null,
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
                MesajGonderildi::dispatch($mesaj);

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
            $character = $user->aiCharacter()->first();
            $code = Language::normalizeCode(data_get($character?->character_json, 'languages.primary_language_code'))
                ?: Language::normalizeCode($user->dil)
                ?: 'tr';

            return [
                'code' => $code,
                'name' => data_get($character?->character_json, 'languages.primary_language_name') ?: Language::name($code),
            ];
        }

        $code = Language::normalizeCode($user->dil);

        return [
            'code' => $code,
            'name' => Language::name($code),
        ];
    }
}
