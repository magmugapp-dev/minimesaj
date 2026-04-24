<?php

namespace App\Services\YapayZeka;

use App\Contracts\AiSaglayiciInterface;
use App\Exceptions\AiSaglayiciHatasi;
use App\Models\AiAyar;
use App\Models\AiHafiza;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\InstagramMesaj;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiServisi
{
    private const BAGLAM_MESAJ_LIMITI = 7;
    private const HAFIZA_LIMITI = 10;

    private AiSaglayiciInterface $saglayici;
    private AiKullaniciHazirlamaServisi $aiKullaniciHazirlamaServisi;

    public function __construct(private array $saglayicilar = [])
    {
        $this->saglayici = $this->saglayicilar['gemini'] ?? new GeminiSaglayici();
        $this->aiKullaniciHazirlamaServisi = app(AiKullaniciHazirlamaServisi::class);
    }

    public function datingCevapUret(
        Sohbet $sohbet,
        Mesaj $gelenMesaj,
        User $aiUser,
        ?callable $parcaCallback = null
    ): array {
        $ayar = $this->hazirAiAyariBul($aiUser);
        $mesajlar = $this->baglamOlustur($sohbet, $gelenMesaj, $aiUser, $ayar);
        return $this->yapilandirilmisSonucUret($mesajlar, $parcaCallback);
    }

    public function datingIlkMesajUret(
        Sohbet $sohbet,
        User $aiUser,
        ?callable $parcaCallback = null
    ): array {
        $ayar = $this->hazirAiAyariBul($aiUser);
        $mesajlar = $this->ilkMesajBaglamOlustur($sohbet, $aiUser, $ayar);

        return $this->yapilandirilmisSonucUret($mesajlar, $parcaCallback);
    }

    public function instagramCevapUret(
        InstagramHesap $hesap,
        InstagramKisi $kisi,
        InstagramMesaj $gelenMesaj,
        ?callable $parcaCallback = null
    ): array {
        $aiUser = $hesap->user;
        $ayar = $this->hazirAiAyariBul($aiUser);
        $mesajlar = $this->instagramBaglamOlustur($hesap, $kisi, $ayar, $gelenMesaj);
        return $this->yapilandirilmisSonucUret($mesajlar, $parcaCallback);
    }

    public function datingHafizaKaydet(
        Sohbet $sohbet,
        Mesaj $gelenMesaj,
        User $aiUser,
        array $kayitlar,
    ): void {
        $ayar = $this->hazirAiAyariBul($aiUser);

        if (!$ayar->hafiza_aktif_mi || !$this->hafizaIcinUygunDatingMesajiMi($gelenMesaj, $aiUser)) {
            return;
        }

        $hedefUserId = $this->datingHedefUserIdBul($sohbet, $aiUser);

        $this->hafizaKayitlariniKaydet(
            $aiUser->id,
            AiHafiza::HEDEF_TIPI_USER,
            $hedefUserId,
            $sohbet->id,
            $gelenMesaj->id,
            $kayitlar
        );
    }

    public function instagramHafizaKaydet(
        InstagramHesap $hesap,
        InstagramKisi $kisi,
        InstagramMesaj $gelenMesaj,
        array $kayitlar,
    ): void {
        $aiUser = $hesap->user;
        $ayar = $this->hazirAiAyariBul($aiUser);

        if (!$ayar->hafiza_aktif_mi || !$this->hafizaIcinUygunInstagramMesajiMi($gelenMesaj)) {
            return;
        }

        $this->hafizaKayitlariniKaydet(
            $aiUser->id,
            AiHafiza::HEDEF_TIPI_INSTAGRAM_KISI,
            $kisi->id,
            null,
            $gelenMesaj->id,
            $kayitlar
        );
    }

    private function hazirAiAyariBul(User $aiUser): AiAyar
    {
        $ayar = $aiUser->aiAyar;

        if (
            !$ayar
            || !$ayar->aktif_mi
            || $ayar->saglayici_tipi !== 'gemini'
            || blank($ayar->model_adi)
            || GeminiModelPolicy::normalizeConfiguredModel($ayar->model_adi) !== $ayar->model_adi
        ) {
            return $this->aiKullaniciHazirlamaServisi->hazirla($aiUser);
        }

        return $ayar;
    }

    private function yapilandirilmisSonucUret(
        array $mesajlar,
        ?callable $parcaCallback = null
    ): array {
        $ayar = \App\Models\Ayar::query();
        $parametreler = [
            'model_adi' => GeminiSaglayici::MODEL_ADI,
            'temperature' => (float) ($ayar->where('anahtar', 'ai_temperature')->value('deger') ?: 0.9),
            'top_p' => 0.95,
            'max_output_tokens' => (int) ($ayar->where('anahtar', 'ai_max_token')->value('deger') ?: 1024),
        ];
        $sonuc = $this->saglayici->tamamlaStream($mesajlar, $parametreler, $parcaCallback);
        $ayristirilmis = $this->yapilandirilmisYanitiAyristir($sonuc['cevap'] ?? '');

        $sonuc['ham_cevap'] = $sonuc['cevap'] ?? '';
        $sonuc['cevap'] = $ayristirilmis['reply'];
        $sonuc['hafiza_kayitlari'] = $ayristirilmis['memory'];
        $sonuc['gecikme'] = $ayristirilmis['delay']
            || $this->kapanisTonluMesajMi($ayristirilmis['reply']);

        return $sonuc;
    }



    private function openaiKeyMevcutMu(): bool
    {
        $key = \App\Models\Ayar::where('anahtar', 'openai_api_key')->value('deger');

        return !empty($key);
    }

    private function baglamOlustur(Sohbet $sohbet, Mesaj $gelenMesaj, User $aiUser, AiAyar $ayar): array
    {
        $hedefUserId = $this->datingHedefUserIdBul($sohbet, $aiUser);
        $hafizalar = $ayar->hafiza_aktif_mi
            ? $this->hafizalariGetir($aiUser->id, AiHafiza::HEDEF_TIPI_USER, $hedefUserId)
            : [];

        $mesajlar = [];
        $mesajlar[] = [
            'role' => 'system',
            'content' => $this->yapilandirilmisSistemIcerigiOlustur(
                $this->sistemKomutuOlustur($aiUser, $ayar),
                $hafizalar,
                'Bu kullanici hakkinda hatirladiklarin'
            ),
        ];

        $sonMesajlar = $this->sonSohbetMesajlariniGetir($sohbet, $gelenMesaj);

        foreach ($sonMesajlar as $mesaj) {
            $mesajlar[] = [
                'role' => $mesaj->gonderen_user_id === $aiUser->id ? 'assistant' : 'user',
                'content' => $mesaj->mesaj_metni ?? '[medya]',
            ];
        }

        return $mesajlar;
    }

    private function ilkMesajBaglamOlustur(Sohbet $sohbet, User $aiUser, AiAyar $ayar): array
    {
        $hedefUserId = $this->datingHedefUserIdBul($sohbet, $aiUser);
        $hedefUser = User::query()->find($hedefUserId);
        $hafizalar = $ayar->hafiza_aktif_mi
            ? $this->hafizalariGetir($aiUser->id, AiHafiza::HEDEF_TIPI_USER, $hedefUserId)
            : [];

        $profilSatirlari = [
            'Ad: ' . ($hedefUser?->ad ?: 'Bilinmiyor'),
            'Kullanici adi: ' . ($hedefUser?->kullanici_adi ?: 'Bilinmiyor'),
            'Biyografi: ' . ($hedefUser?->biyografi ?: 'Belirtilmemis'),
            'Sehir: ' . ($hedefUser?->il ?: 'Belirtilmemis'),
            'Ulke: ' . ($hedefUser?->ulke ?: 'Belirtilmemis'),
        ];

        $ilkMesajTalimati = implode("\n", array_filter([
            'Bu kisiyle yeni eslestin ve ilk mesaji sen atacaksin.',
            'Tek bir acilis mesaji yaz. Dogal, sicak ve insan gibi olsun.',
            'Baski kurma, cok uzun yazma, yapay veya robotik konusma.',
            'Gerekirse hafif flortoz ama kibar kal.',
            $ayar->ilk_mesaj_sablonu
                ? 'Su acilis hissine yakin kal: ' . $ayar->ilk_mesaj_sablonu
                : null,
            'Karsi taraf profili:',
            ...$profilSatirlari,
        ]));

        return [[
            'role' => 'system',
            'content' => $this->yapilandirilmisSistemIcerigiOlustur(
                $this->sistemKomutuOlustur($aiUser, $ayar),
                $hafizalar,
                'Bu kullanici hakkinda hatirladiklarin'
            ),
        ], [
            'role' => 'user',
            'content' => $ilkMesajTalimati,
        ]];
    }

    private function instagramBaglamOlustur(
        InstagramHesap $hesap,
        InstagramKisi $kisi,
        AiAyar $ayar,
        InstagramMesaj $gelenMesaj,
    ): array {
        $aiUser = $hesap->user;
        $hafizalar = $ayar->hafiza_aktif_mi
            ? $this->hafizalariGetir($aiUser->id, AiHafiza::HEDEF_TIPI_INSTAGRAM_KISI, $kisi->id)
            : [];

        $mesajlar = [];
        $mesajlar[] = [
            'role' => 'system',
            'content' => $this->yapilandirilmisSistemIcerigiOlustur(
                $this->instagramSistemKomutu($aiUser, $ayar, $kisi),
                $hafizalar,
                'Bu kisi hakkinda hatirladiklarin'
            ),
        ];

        $sonMesajlar = $this->sonInstagramMesajlariniGetir($hesap, $kisi, $gelenMesaj);

        foreach ($sonMesajlar as $mesaj) {
            $mesajlar[] = [
                'role' => $mesaj->gonderen_tipi === 'karsi_taraf' ? 'user' : 'assistant',
                'content' => $mesaj->mesaj_metni ?? '[medya]',
            ];
        }

        return $mesajlar;
    }

    private function yapilandirilmisSistemIcerigiOlustur(
        string $temelSistemMesaji,
        array $hafizalar,
        string $hafizaBasligi,
    ): string {
        $parcalar = [$temelSistemMesaji];

        if ($hafizalar !== []) {
            $parcalar[] = $hafizaBasligi . ":\n- " . implode("\n- ", $hafizalar);
        }

        $parcalar[] = $this->yapilandirilmisCevapTalimatlari();

        return implode("\n\n", array_filter($parcalar));
    }

    private function yapilandirilmisCevapTalimatlari(): string
    {
        $izinliKonular = collect(AiHafiza::izinliKonuTanimlari())
            ->map(function (array $tanim, string $anahtar) {
                return "- {$anahtar} ({$tanim['hafiza_tipi']}): {$tanim['aciklama']}";
            })
            ->implode("\n");

        return implode("\n", [
            'CIKTI FORMATI:',
            'Yalnizca tek bir JSON object dondur. JSON disi hicbir metin, aciklama, markdown veya kod blogu ekleme.',
            'JSON formati tam olarak su yapida olsun:',
            '{"reply":"kullaniciya gidecek nihai mesaj","memory":[{"hafiza_tipi":"bilgi","konu_anahtari":"memleket","icerik":"Aslen Izmirli.","onem_puani":7}],"gecikme":false}',
            'reply alani kullaniciya gidecek tek nihai cevaptir. Dogal, kisa ve akici yaz.',
            'memory alani sadece karsi tarafin kendisi hakkinda acikca soyledigi, kisa ve hatirlanabilir bilgileri icersin.',
            'gecikme alani sadece boolean true/false olsun.',
            'Eger cevap konusmayi dogal olarak kapatiyor, bir sure sonra devam edelim hissi veriyor veya "iyi geceler", "gorusuruz", "yarin yazarim", "simdi cikmam lazim" gibi bir kapanis tonu tasiyorsa gecikme=true yaz.',
            'Normal akista konusma devam ediyorsa gecikme=false yaz.',
            'Tahmin, yorum, ima, abarti, AI mesaji veya baska kisiye ait bilgiyi memory alanina yazma.',
            'Uygun memory yoksa memory icin bos array [] kullan.',
            'memory icindeki her oge tek cümlelik kisa bir not olsun ve 140 karakteri gecmesin.',
            'Sadece asagidaki konu_anahtari listesinden sec:',
            $izinliKonular,
        ]);
    }

    private function yapilandirilmisYanitiAyristir(string $hamMetin): array
    {
        $temizMetin = trim($hamMetin);

        if ($temizMetin === '') {
            throw new AiSaglayiciHatasi('AI bos bir cevap dondu.', 'ai');
        }

        $json = $this->jsonObjesiniBul($temizMetin);

        if ($json === null) {
            return [
                'reply' => $this->hamReplyKurtar($temizMetin),
                'memory' => [],
                'delay' => false,
            ];
        }

        $veri = json_decode($json, true);

        if (!is_array($veri)) {
            return [
                'reply' => $this->hamReplyKurtar($temizMetin),
                'memory' => [],
                'delay' => false,
            ];
        }

        $reply = trim((string) ($veri['reply'] ?? ''));

        if ($reply === '') {
            throw new AiSaglayiciHatasi(
                'AI yapilandirilmis cevap dondu ama reply alani bos kaldi.',
                'ai'
            );
        }

        return [
            'reply' => $reply,
            'memory' => $this->hafizaDizisiniNormalizeEt($veri['memory'] ?? []),
            'delay' => $this->gecikmeBayraginiNormalizeEt($veri),
        ];
    }

    private function gecikmeBayraginiNormalizeEt(array $veri): bool
    {
        $deger = $veri['gecikme']
            ?? $veri['delay']
            ?? $veri['cooldown']
            ?? false;

        if (is_bool($deger)) {
            return $deger;
        }

        if (is_numeric($deger)) {
            return (int) $deger === 1;
        }

        return in_array(mb_strtolower(trim((string) $deger)), ['1', 'true', 'evet', 'yes'], true);
    }

    private function jsonObjesiniBul(string $hamMetin): ?string
    {
        $temizMetin = trim($hamMetin);
        $temizMetin = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $temizMetin) ?? $temizMetin;

        $baslangic = strpos($temizMetin, '{');
        $bitis = strrpos($temizMetin, '}');

        if ($baslangic === false || $bitis === false || $bitis < $baslangic) {
            return null;
        }

        return substr($temizMetin, $baslangic, $bitis - $baslangic + 1);
    }

    private function hamReplyKurtar(string $hamMetin): string
    {
        $cevap = trim($hamMetin);
        $cevap = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cevap) ?? $cevap;
        $cevap = trim($cevap);

        $jsonReply = $this->jsonIcerigindenReplyKurtar($cevap);
        if ($jsonReply !== null) {
            return $jsonReply;
        }

        if ($cevap === '') {
            throw new AiSaglayiciHatasi('AI cevabi parse edilemedi ve reply kurtarilamadi.', 'ai');
        }

        return $cevap;
    }

    private function jsonIcerigindenReplyKurtar(string $hamMetin): ?string
    {
        if (!str_contains($hamMetin, '"reply"')) {
            return null;
        }

        if (preg_match('/"reply"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/u', $hamMetin, $eslesme)) {
            $cozulmus = json_decode('"' . $eslesme[1] . '"');

            if (is_string($cozulmus)) {
                $cozulmus = trim($cozulmus);

                return $cozulmus === '' ? null : $cozulmus;
            }
        }

        if (!preg_match('/"reply"\s*:\s*"([\s\S]*)$/u', $hamMetin, $eslesme)) {
            return null;
        }

        $hamReply = preg_replace('/\s*"memory"\s*:\s*\[[\s\S]*$/u', '', $eslesme[1]) ?? $eslesme[1];
        $hamReply = preg_replace('/\s*"gecikme"\s*:\s*(true|false)[\s\S]*$/ui', '', $hamReply) ?? $hamReply;
        $hamReply = rtrim($hamReply, "\", \t\n\r\0\x0B}");
        $hamReply = stripcslashes($hamReply);
        $hamReply = trim($hamReply);

        return $hamReply === '' ? null : $hamReply;
    }

    private function kapanisTonluMesajMi(string $metin): bool
    {
        $normalize = Str::of($metin)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();

        $ifadeler = [
            'gorusuruz',
            'sonra gorusuruz',
            'iyi geceler',
            'tatli ruyalar',
            'yarin konusuruz',
            'yarin yazarim',
            'simdi cikmam lazim',
            'simdi kacmam lazim',
            'ben kacayim',
            'artik uyuyayim',
            'sonra yazarim',
            'kendine iyi bak',
        ];

        foreach ($ifadeler as $ifade) {
            if (str_contains($normalize, $ifade)) {
                return true;
            }
        }

        return false;
    }

    private function hafizalariGetir(int $aiUserId, string $hedefTipi, int $hedefId): array
    {
        return AiHafiza::query()
            ->where('ai_user_id', $aiUserId)
            ->hedef($hedefTipi, $hedefId)
            ->aktif()
            ->orderByDesc('onem_puani')
            ->orderByDesc('updated_at')
            ->limit(self::HAFIZA_LIMITI)
            ->pluck('icerik')
            ->filter()
            ->values()
            ->all();
    }

    private function sonSohbetMesajlariniGetir(Sohbet $sohbet, ?Mesaj $gelenMesaj = null): Collection
    {
        $sonMesajlar = Mesaj::where('sohbet_id', $sohbet->id)
            ->where('created_at', '>=', now()->subHours(48))
            ->orderByDesc('id')
            ->limit(self::BAGLAM_MESAJ_LIMITI)
            ->get();

        if ($sonMesajlar->isEmpty()) {
            $sonMesajlar = Mesaj::where('sohbet_id', $sohbet->id)
                ->orderByDesc('id')
                ->limit(self::BAGLAM_MESAJ_LIMITI)
                ->get();
        }

        return $this->mesajKoleksiyonunuHazirla($sonMesajlar, $gelenMesaj);
    }

    private function sonInstagramMesajlariniGetir(
        InstagramHesap $hesap,
        InstagramKisi $kisi,
        ?InstagramMesaj $gelenMesaj = null,
    ): Collection {
        $sonMesajlar = InstagramMesaj::where('instagram_hesap_id', $hesap->id)
            ->where('instagram_kisi_id', $kisi->id)
            ->where('created_at', '>=', now()->subHours(48))
            ->orderByDesc('id')
            ->limit(self::BAGLAM_MESAJ_LIMITI)
            ->get();

        if ($sonMesajlar->isEmpty()) {
            $sonMesajlar = InstagramMesaj::where('instagram_hesap_id', $hesap->id)
                ->where('instagram_kisi_id', $kisi->id)
                ->orderByDesc('id')
                ->limit(self::BAGLAM_MESAJ_LIMITI)
                ->get();
        }

        return $this->mesajKoleksiyonunuHazirla($sonMesajlar, $gelenMesaj);
    }

    private function mesajKoleksiyonunuHazirla(Collection $mesajlar, $gelenMesaj = null): Collection
    {
        if ($gelenMesaj && !$mesajlar->contains('id', $gelenMesaj->id)) {
            $mesajlar = $mesajlar->push($gelenMesaj);
        }

        $mesajlar = $mesajlar
            ->sortBy('id')
            ->values();

        if ($mesajlar->count() > self::BAGLAM_MESAJ_LIMITI) {
            $mesajlar = $mesajlar->slice(-self::BAGLAM_MESAJ_LIMITI)->values();
        }

        return $mesajlar;
    }

    private function datingHedefUserIdBul(Sohbet $sohbet, User $aiUser): int
    {
        $eslesme = $sohbet->eslesme;

        return $eslesme->user_id === $aiUser->id
            ? $eslesme->eslesen_user_id
            : $eslesme->user_id;
    }

    private function hafizaIcinUygunDatingMesajiMi(Mesaj $gelenMesaj, User $aiUser): bool
    {
        return (int) $gelenMesaj->gonderen_user_id !== (int) $aiUser->id
            && $this->metinHafizaIcinUygunMu($gelenMesaj->mesaj_metni);
    }

    private function hafizaIcinUygunInstagramMesajiMi(InstagramMesaj $gelenMesaj): bool
    {
        return $gelenMesaj->gonderen_tipi === 'karsi_taraf'
            && $this->metinHafizaIcinUygunMu($gelenMesaj->mesaj_metni);
    }

    private function metinHafizaIcinUygunMu(?string $metin): bool
    {
        return trim((string) $metin) !== '';
    }

    private function hafizaDizisiniNormalizeEt(mixed $hamMemory): array
    {
        if (!is_array($hamMemory)) {
            return [];
        }

        $tekillesmisKayitlar = [];

        foreach ($hamMemory as $hamKayit) {
            $kayit = $this->hafizaKaydiniNormalizeEt($hamKayit);

            if ($kayit === null) {
                continue;
            }

            $tekillesmisKayitlar[$kayit['konu_anahtari']] = $kayit;
        }

        return array_values($tekillesmisKayitlar);
    }

    private function hafizaKaydiniNormalizeEt(mixed $hamKayit): ?array
    {
        if (!is_array($hamKayit)) {
            return null;
        }

        $konuAnahtari = Str::of((string) ($hamKayit['konu_anahtari'] ?? ''))
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_')
            ->value();

        $konuTanimi = AiHafiza::konuTanimi($konuAnahtari);

        if (!$konuTanimi) {
            return null;
        }

        $icerik = trim(preg_replace('/\s+/', ' ', (string) ($hamKayit['icerik'] ?? '')) ?? '');

        if ($icerik === '') {
            return null;
        }

        $icerik = Str::limit($icerik, 140, '...');
        $onemPuani = (int) ($hamKayit['onem_puani'] ?? 5);
        $onemPuani = max(1, min(10, $onemPuani));

        return [
            'hafiza_tipi' => $konuTanimi['hafiza_tipi'],
            'konu_anahtari' => $konuAnahtari,
            'icerik' => $icerik,
            'onem_puani' => $onemPuani,
            'son_kullanma_tarihi' => $konuTanimi['hafiza_tipi'] === AiHafiza::HAFIZA_TIPI_DUYGU
                ? now()->addDays($konuTanimi['ttl_gun'] ?? AiHafiza::DUYGU_TTL_GUN)
                : null,
        ];
    }

    private function hafizaKayitlariniKaydet(
        int $aiUserId,
        string $hedefTipi,
        int $hedefId,
        ?int $sohbetId,
        ?int $kaynakMesajId,
        array $kayitlar,
    ): void {
        if ($kayitlar === []) {
            return;
        }

        DB::transaction(function () use (
            $aiUserId,
            $hedefTipi,
            $hedefId,
            $sohbetId,
            $kaynakMesajId,
            $kayitlar,
        ) {
            foreach ($kayitlar as $kayit) {
                AiHafiza::updateOrCreate(
                    [
                        'ai_user_id' => $aiUserId,
                        'hedef_tipi' => $hedefTipi,
                        'hedef_id' => $hedefId,
                        'konu_anahtari' => $kayit['konu_anahtari'],
                    ],
                    [
                        'sohbet_id' => $sohbetId,
                        'hafiza_tipi' => $kayit['hafiza_tipi'],
                        'icerik' => $kayit['icerik'],
                        'onem_puani' => $kayit['onem_puani'],
                        'kaynak_mesaj_id' => $kaynakMesajId,
                        'son_kullanma_tarihi' => $kayit['son_kullanma_tarihi'],
                    ]
                );
            }
        });
    }

    private function sistemKomutuOlustur(User $aiUser, AiAyar $ayar): string
    {
        $komut = $ayar->sistem_komutu ?? '';

        $varsayilan = "Sen {$aiUser->ad} adinda bir kisisin. "
            . "Kisilik tipi: {$ayar->kisilik_tipi}. "
            . "Konusma tonu: {$ayar->konusma_tonu}, stili: {$ayar->konusma_stili}. "
            . "Flort seviyesi: {$ayar->flort_seviyesi}/10. "
            . "Mizah: {$ayar->mizah_seviyesi}/10, giriskenlik: {$ayar->giriskenlik_seviyesi}/10. "
            . "Mesajlarini {$ayar->mesaj_uzunlugu_min}-{$ayar->mesaj_uzunlugu_max} karakter arasinda tut.";

        $varsayilan .= $this->emojiTalimatiOlustur($ayar->emoji_seviyesi ?? 5);

        if ($ayar->yasakli_konular && count($ayar->yasakli_konular) > 0) {
            $yasakListesi = implode(', ', $ayar->yasakli_konular);
            $varsayilan .= "\n\nYASAKLI KONULAR:\n"
                . "Asagidaki konulari konusma, bunlarla ilgili soru sorma, cevap verme veya ima etme. "
                . "Karsi taraf bu konulardan bahsederse kibarca konuyu degistir ve \"Bu konuda konusmayi tercih etmiyorum\" de.\n"
                . "Yasakli konular: {$yasakListesi}";
        }

        if ($ayar->zorunlu_kurallar && count($ayar->zorunlu_kurallar) > 0) {
            $varsayilan .= "\n\nZORUNLU KURALLAR:\n";
            foreach ($ayar->zorunlu_kurallar as $i => $kural) {
                $varsayilan .= ($i + 1) . ". {$kural}\n";
            }
        }

        return $komut ? "{$komut}\n\n{$varsayilan}" : $varsayilan;
    }

    private function emojiTalimatiOlustur(int $seviye): string
    {
        if ($seviye === 0) {
            return "\n\nEMOJI KURALI: Mesajlarinda hicbir emoji, emoticon veya ozel karakter kullanma.";
        }

        if ($seviye <= 2) {
            return "\nEmoji kullanimi: Cok nadir, sadece cok gerektiginde en fazla 1 emoji kullan.";
        }

        if ($seviye <= 4) {
            return "\nEmoji kullanimi: Az miktarda, mesaj basina en fazla 1-2 emoji kullanabilirsin.";
        }

        if ($seviye <= 6) {
            return "\nEmoji kullanimi: Orta duzeyde, dogal akan yerlerde emoji kullanabilirsin.";
        }

        if ($seviye <= 8) {
            return "\nEmoji kullanimi: Sikca emoji kullan, mesajlarini emojilerle renklendir.";
        }

        return "\nEmoji kullanimi: Cok yogun emoji kullan, neredeyse her cumlede emoji olsun.";
    }

    private function instagramSistemKomutu(User $aiUser, AiAyar $ayar, InstagramKisi $kisi): string
    {
        $temel = $this->sistemKomutuOlustur($aiUser, $ayar);

        return "{$temel}\n\nBu bir Instagram DM sohbetidir. Karsi tarafin kullanici adi: @{$kisi->kullanici_adi}, "
            . "gorunen adi: {$kisi->gorunen_ad}. Instagram'daki dogal bir sohbet gibi yaz.";
    }

    // saglayiciBul fonksiyonu ve $saglayicilar propertysi kaldırıldı. Sadece GeminiSaglayici kullanılacak.

    private function hataKaydiOlustur(string $saglayici, ?string $model, \Throwable $e): array
    {
        return [
            'saglayici' => $saglayici,
            'model' => $this->normalizeModel($saglayici, $model),
            'mesaj' => $e->getMessage(),
            'yeniden_denenebilir' => $e instanceof AiSaglayiciHatasi ? $e->yenidenDenenebilir : false,
            'durum_kodu' => $e instanceof AiSaglayiciHatasi ? $e->durumKodu : null,
        ];
    }

    private function normalizeModel(?string $saglayici, ?string $model): ?string
    {
        if ($saglayici === 'gemini') {
            return GeminiModelPolicy::normalizeConfiguredModel($model);
        }

        return $model;
    }

    private function topluHataOlustur(array $hatalar): AiSaglayiciHatasi
    {
        if ($hatalar === []) {
            return new AiSaglayiciHatasi('AI cevap uretilemedi.', 'ai');
        }

        $ozet = collect($hatalar)
            ->map(function (array $hata) {
                $parcalar = array_filter([
                    $hata['saglayici'] ?? null,
                    $hata['model'] ?? null,
                    isset($hata['durum_kodu']) && $hata['durum_kodu'] ? 'HTTP ' . $hata['durum_kodu'] : null,
                ]);

                return implode('/', $parcalar) . ': ' . ($hata['mesaj'] ?? 'Bilinmeyen hata');
            })
            ->implode(' | ');

        return new AiSaglayiciHatasi(
            'AI cevap uretilemedi. ' . $ozet,
            'ai',
            null,
            collect($hatalar)->contains(fn(array $hata) => $hata['yeniden_denenebilir'] === true),
            null,
            ['denemeler' => $hatalar]
        );
    }
}
