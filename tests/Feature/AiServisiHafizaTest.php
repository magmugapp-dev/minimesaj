<?php

use App\Contracts\AiSaglayiciInterface;
use App\Models\AiAyar;
use App\Models\AiHafiza;
use App\Models\Eslesme;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\InstagramMesaj;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\YapayZeka\AiServisi;
use Illuminate\Support\Carbon;

class SahteAiSaglayici implements AiSaglayiciInterface
{
    public array $cagrilar = [];

    public function __construct(private array $yanitlar) {}

    public function tamamla(array $mesajlar, array $parametreler = []): array
    {
        return $this->tamamlaStream($mesajlar, $parametreler);
    }

    public function tamamlaStream(
        array $mesajlar,
        array $parametreler = [],
        ?callable $parcaCallback = null
    ): array {
        $this->cagrilar[] = [
            'mesajlar' => $mesajlar,
            'parametreler' => $parametreler,
        ];

        $yanit = array_shift($this->yanitlar) ?? '{"reply":"varsayilan","memory":[]}';

        if ($parcaCallback) {
            $parcaCallback($yanit, [
                'stream' => false,
                'model' => $parametreler['model_adi'] ?? 'fake-model',
            ]);
        }

        return [
            'cevap' => $yanit,
            'giris_token' => 0,
            'cikis_token' => 0,
            'model' => $parametreler['model_adi'] ?? 'fake-model',
        ];
    }

    public function saglayiciAdi(): string
    {
        return 'gemini';
    }
}

function yapilandirilmisYanit(string $reply, array $memory = [], bool $gecikme = false): string
{
    return json_encode([
        'reply' => $reply,
        'memory' => $memory,
        'gecikme' => $gecikme,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function aiAyariOlustur(User $aiUser): AiAyar
{
    return AiAyar::create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-3.1-auto-quality',
        'hafiza_aktif_mi' => true,
        'kisilik_tipi' => 'samimi',
        'konusma_tonu' => 'dogal',
        'konusma_stili' => 'kisa',
        'mesaj_uzunlugu_min' => 10,
        'mesaj_uzunlugu_max' => 120,
    ]);
}

function datingSohbetiOlustur(User $aiUser, User $hedefUser): Sohbet
{
    $eslesme = Eslesme::create([
        'user_id' => $aiUser->id,
        'eslesen_user_id' => $hedefUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
    ]);

    return Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
    ]);
}

it('limits dating reply context to the last 7 messages in a single provider call', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();
    aiAyariOlustur($aiUser);
    $sohbet = datingSohbetiOlustur($aiUser, $hedefUser);

    $sonMesaj = null;

    foreach (range(1, 10) as $sira) {
        $sonMesaj = Mesaj::create([
            'sohbet_id' => $sohbet->id,
            'gonderen_user_id' => $sira % 2 === 0 ? $aiUser->id : $hedefUser->id,
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => "dating mesaj {$sira}",
        ]);
    }

    $saglayici = new SahteAiSaglayici([yapilandirilmisYanit('tamam')]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->datingCevapUret($sohbet, $sonMesaj, $aiUser);
    $gonderilenMesajlar = $saglayici->cagrilar[0]['mesajlar'];

    expect($saglayici->cagrilar)->toHaveCount(1);
    expect($sonuc['cevap'])->toBe('tamam');
    expect($sonuc['hafiza_kayitlari'])->toBe([]);
    expect($gonderilenMesajlar)->toHaveCount(8);
    expect(array_slice(array_column($gonderilenMesajlar, 'content'), 1))->toBe([
        'dating mesaj 4',
        'dating mesaj 5',
        'dating mesaj 6',
        'dating mesaj 7',
        'dating mesaj 8',
        'dating mesaj 9',
        'dating mesaj 10',
    ]);
});

it('limits instagram reply context to the last 7 messages in a single provider call', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    aiAyariOlustur($aiUser);

    $hesap = InstagramHesap::create([
        'user_id' => $aiUser->id,
        'instagram_kullanici_adi' => 'ai.dm',
    ]);

    $kisi = InstagramKisi::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => 'target-1',
        'kullanici_adi' => 'real.person',
        'gorunen_ad' => 'Real Person',
    ]);

    $sonMesaj = null;

    foreach (range(1, 10) as $sira) {
        $sonMesaj = InstagramMesaj::create([
            'instagram_hesap_id' => $hesap->id,
            'instagram_kisi_id' => $kisi->id,
            'gonderen_tipi' => $sira % 2 === 0 ? 'ai' : 'karsi_taraf',
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => "instagram mesaj {$sira}",
        ]);
    }

    $saglayici = new SahteAiSaglayici([yapilandirilmisYanit('tamam')]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->instagramCevapUret($hesap, $kisi, $sonMesaj);
    $gonderilenMesajlar = $saglayici->cagrilar[0]['mesajlar'];

    expect($saglayici->cagrilar)->toHaveCount(1);
    expect($sonuc['cevap'])->toBe('tamam');
    expect($sonuc['hafiza_kayitlari'])->toBe([]);
    expect($gonderilenMesajlar)->toHaveCount(8);
    expect(array_slice(array_column($gonderilenMesajlar, 'content'), 1))->toBe([
        'instagram mesaj 4',
        'instagram mesaj 5',
        'instagram mesaj 6',
        'instagram mesaj 7',
        'instagram mesaj 8',
        'instagram mesaj 9',
        'instagram mesaj 10',
    ]);
});

it('extracts and saves structured memories from the same dating response', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();
    aiAyariOlustur($aiUser);
    $sohbet = datingSohbetiOlustur($aiUser, $hedefUser);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $hedefUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Ben lise mezunuyum, universiteyi de Bogazici\'nde bitirdim.',
    ]);

    $saglayici = new SahteAiSaglayici([
        yapilandirilmisYanit('Harika, emegine saglik.', [
            [
                'hafiza_tipi' => 'bilgi',
                'konu_anahtari' => 'egitim_lise_durum',
                'icerik' => 'Lise mezunu.',
                'onem_puani' => 6,
            ],
            [
                'hafiza_tipi' => 'bilgi',
                'konu_anahtari' => 'egitim_universite_durum',
                'icerik' => 'Universite mezunu.',
                'onem_puani' => 8,
            ],
            [
                'hafiza_tipi' => 'bilgi',
                'konu_anahtari' => 'egitim_universite_adi',
                'icerik' => 'Bogazici mezunu.',
                'onem_puani' => 9,
            ],
        ]),
    ]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->datingCevapUret($sohbet, $mesaj, $aiUser);
    $servis->datingHafizaKaydet($sohbet, $mesaj, $aiUser, $sonuc['hafiza_kayitlari']);

    $kayitlar = AiHafiza::query()
        ->where('ai_user_id', $aiUser->id)
        ->where('hedef_tipi', AiHafiza::HEDEF_TIPI_USER)
        ->where('hedef_id', $hedefUser->id)
        ->orderBy('konu_anahtari')
        ->get();

    expect($saglayici->cagrilar)->toHaveCount(1);
    expect($sonuc['cevap'])->toBe('Harika, emegine saglik.');
    expect($kayitlar)->toHaveCount(3);
    expect($kayitlar->pluck('konu_anahtari')->all())->toBe([
        'egitim_lise_durum',
        'egitim_universite_adi',
        'egitim_universite_durum',
    ]);
});

it('stores instagram emotion memories with a 3 day ttl from the same response', function () {
    Carbon::setTestNow('2026-04-09 18:00:00');

    $aiUser = User::factory()->aiKullanici()->create();
    aiAyariOlustur($aiUser);

    $hesap = InstagramHesap::create([
        'user_id' => $aiUser->id,
        'instagram_kullanici_adi' => 'ai.memory',
    ]);

    $kisi = InstagramKisi::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => 'target-2',
        'kullanici_adi' => 'mood.user',
        'gorunen_ad' => 'Mood User',
    ]);

    $mesaj = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'gonderen_tipi' => 'karsi_taraf',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Bugun moralim biraz bozuk.',
    ]);

    $saglayici = new SahteAiSaglayici([
        yapilandirilmisYanit('Umarim biraz daha iyi hissedersin.', [
            [
                'hafiza_tipi' => 'duygu',
                'konu_anahtari' => 'duygu_genel',
                'icerik' => 'Bugun morali biraz bozuk.',
                'onem_puani' => 6,
            ],
        ]),
    ]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->instagramCevapUret($hesap, $kisi, $mesaj);
    $servis->instagramHafizaKaydet($hesap, $kisi, $mesaj, $sonuc['hafiza_kayitlari']);

    $kayit = AiHafiza::query()
        ->where('ai_user_id', $aiUser->id)
        ->where('hedef_tipi', AiHafiza::HEDEF_TIPI_INSTAGRAM_KISI)
        ->where('hedef_id', $kisi->id)
        ->where('konu_anahtari', 'duygu_genel')
        ->first();

    expect($saglayici->cagrilar)->toHaveCount(1);
    expect($kayit)->not->toBeNull();
    expect($kayit->hafiza_tipi)->toBe(AiHafiza::HAFIZA_TIPI_DUYGU);
    expect($kayit->icerik)->toBe('Bugun morali biraz bozuk.');
    expect($kayit->son_kullanma_tarihi?->equalTo(now()->addDays(3)))->toBeTrue();

    Carbon::setTestNow();
});

it('falls back to plain reply when structured json cannot be parsed', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();
    aiAyariOlustur($aiUser);
    $sohbet = datingSohbetiOlustur($aiUser, $hedefUser);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $hedefUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam, nasilsin?',
    ]);

    $saglayici = new SahteAiSaglayici(['Ben iyiyim, sen nasilsin?']);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->datingCevapUret($sohbet, $mesaj, $aiUser);

    expect($saglayici->cagrilar)->toHaveCount(1);
    expect($sonuc['cevap'])->toBe('Ben iyiyim, sen nasilsin?');
    expect($sonuc['hafiza_kayitlari'])->toBe([]);
    expect($sonuc['gecikme'])->toBeFalse();
});

it('rescues the reply text from a truncated structured json response', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();
    aiAyariOlustur($aiUser);
    $sohbet = datingSohbetiOlustur($aiUser, $hedefUser);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $hedefUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Beni nasil buluyorsun?',
    ]);

    $hamYanit = '{"reply":"Hmm, su an sadece yazistigimiz icin tam bir sey soylemek zor. Ama merak uyandiran, esprili birisin diyebilirim. Neden sordun ki","memory":[{"hafiza_tipi":"bilgi","konu';

    $saglayici = new SahteAiSaglayici([$hamYanit]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->datingCevapUret($sohbet, $mesaj, $aiUser);

    expect($sonuc['cevap'])->toBe('Hmm, su an sadece yazistigimiz icin tam bir sey soylemek zor. Ama merak uyandiran, esprili birisin diyebilirim. Neden sordun ki');
    expect($sonuc['hafiza_kayitlari'])->toBe([]);
    expect($sonuc['gecikme'])->toBeFalse();
});

it('rescues the partial reply text even when the closing quote is missing', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();
    aiAyariOlustur($aiUser);
    $sohbet = datingSohbetiOlustur($aiUser, $hedefUser);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $hedefUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'o ne?',
    ]);

    $hamYanit = '{"reply":"S der';

    $saglayici = new SahteAiSaglayici([$hamYanit]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->datingCevapUret($sohbet, $mesaj, $aiUser);

    expect($sonuc['cevap'])->toBe('S der');
    expect($sonuc['hafiza_kayitlari'])->toBe([]);
    expect($sonuc['gecikme'])->toBeFalse();
});

it('extracts the cooldown flag from structured dating responses', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();
    aiAyariOlustur($aiUser);
    $sohbet = datingSohbetiOlustur($aiUser, $hedefUser);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $hedefUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Tamam o zaman iyi geceler.',
    ]);

    $saglayici = new SahteAiSaglayici([
        yapilandirilmisYanit('Sana da iyi geceler, yarin yine yazariz.', [], true),
    ]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->datingCevapUret($sohbet, $mesaj, $aiUser);

    expect($sonuc['cevap'])->toBe('Sana da iyi geceler, yarin yine yazariz.');
    expect($sonuc['gecikme'])->toBeTrue();
});

it('infers cooldown from a closing style reply even when the flag is false', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();
    aiAyariOlustur($aiUser);
    $sohbet = datingSohbetiOlustur($aiUser, $hedefUser);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $hedefUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'tamam o zaman',
    ]);

    $saglayici = new SahteAiSaglayici([
        yapilandirilmisYanit('Tamamdir, gorusuruz.', [], false),
    ]);
    $servis = new AiServisi(['gemini' => $saglayici]);

    $sonuc = $servis->datingCevapUret($sohbet, $mesaj, $aiUser);

    expect($sonuc['gecikme'])->toBeTrue();
});
