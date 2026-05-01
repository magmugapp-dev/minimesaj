<?php

namespace App\Services;

use App\Events\MesajGonderildi;
use App\Events\MesajlarOkundu;
use App\Exceptions\MesajlasmaEngeliException;
use App\Models\Engelleme;
use App\Models\Eslesme;
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
            $sohbet->loadMissing('eslesme.user', 'eslesme.eslesenUser');
            $eslesme = $sohbet->eslesme;
            $karsiTarafId = $eslesme->user_id === $gonderen->id
                ? $eslesme->eslesen_user_id
                : $eslesme->user_id;

            if ($this->gonderenKarsiTarafTarafindanEngellenmis((int) $gonderen->id, (int) $karsiTarafId)) {
                throw new MesajlasmaEngeliException();
            }

            $karsiTaraf = (int) $eslesme->user_id === (int) $gonderen->id
                ? $eslesme->eslesenUser
                : $eslesme->user;
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

            $this->updateConversationAfterMessage($sohbet, $eslesme, $mesaj, (int) $karsiTarafId);

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
            $sohbet->loadMissing('eslesme.user', 'eslesme.eslesenUser');
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

            $eslesme = $sohbet->eslesme;
            $karsiTarafId = $eslesme->user_id === $aiUser->id
                ? $eslesme->eslesen_user_id
                : $eslesme->user_id;
            $karsiTaraf = (int) $eslesme->user_id === (int) $aiUser->id
                ? $eslesme->eslesenUser
                : $eslesme->user;
            $this->updateConversationAfterMessage($sohbet, $eslesme, $mesaj, (int) $karsiTarafId);

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
        $sohbet->loadMissing('eslesme');
        $eslesme = $sohbet->eslesme;
        $karsiTarafId = $eslesme->user_id === $okuyan->id
            ? $eslesme->eslesen_user_id
            : $eslesme->user_id;
        $column = $this->unreadColumnForRecipient($eslesme, (int) $okuyan->id);

        if ((int) $sohbet->{$column} <= 0) {
            return 0;
        }

        $guncellenen = Mesaj::where('sohbet_id', $sohbet->id)
            ->where('gonderen_user_id', $karsiTarafId)
            ->where('okundu_mu', false)
            ->update(['okundu_mu' => true]);

        if ($guncellenen > 0) {
            $payload = [$column => 0];
            if ((int) $sohbet->son_mesaj_gonderen_user_id === (int) $karsiTarafId) {
                $payload['son_mesaj_okundu_mu'] = true;
            }
            $sohbet->forceFill($payload)->save();
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

    private function updateConversationAfterMessage(
        Sohbet $sohbet,
        Eslesme $eslesme,
        Mesaj $mesaj,
        int $recipientId,
    ): void {
        $unreadColumn = $this->unreadColumnForRecipient($eslesme, $recipientId);

        $sohbet->forceFill([
            'son_mesaj_id' => $mesaj->id,
            'son_mesaj_gonderen_user_id' => $mesaj->gonderen_user_id,
            'son_mesaj_tarihi' => $mesaj->created_at,
            'son_mesaj_tipi' => $mesaj->mesaj_tipi,
            'son_mesaj_metni' => $this->previewText($mesaj),
            'son_mesaj_okundu_mu' => false,
            'toplam_mesaj_sayisi' => DB::raw('toplam_mesaj_sayisi + 1'),
            $unreadColumn => DB::raw($unreadColumn.' + 1'),
        ])->save();
    }

    private function unreadColumnForRecipient(Eslesme $eslesme, int $recipientId): string
    {
        return (int) $eslesme->user_id === $recipientId
            ? 'user_okunmamis_sayisi'
            : 'eslesen_okunmamis_sayisi';
    }

    private function previewText(Mesaj $mesaj): ?string
    {
        $text = AiMessageTextSanitizer::sanitize($mesaj->mesaj_metni);
        if ($text === null || trim($text) === '') {
            return null;
        }

        return mb_substr(trim($text), 0, 500);
    }
}
