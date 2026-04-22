<?php

use App\Contracts\AiSaglayiciInterface;
use App\Jobs\InstagramAiCevapGorevi;
use App\Models\AiAyar;
use App\Models\InstagramAiGorevi;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\InstagramMesaj;
use App\Models\User;
use App\Services\YapayZeka\AiServisi;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

class InstagramSonMesajSahteSaglayici implements AiSaglayiciInterface
{
    public int $cagriSayisi = 0;

    public function __construct(private ?Closure $callback = null) {}

    public function tamamla(array $mesajlar, array $parametreler = []): array
    {
        return $this->tamamlaStream($mesajlar, $parametreler);
    }

    public function tamamlaStream(
        array $mesajlar,
        array $parametreler = [],
        ?callable $parcaCallback = null
    ): array {
        $this->cagriSayisi++;

        if ($this->callback) {
            ($this->callback)();
        }

        $cevap = json_encode([
            'reply' => 'Son mesaja cevap',
            'memory' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($parcaCallback) {
            $parcaCallback($cevap, [
                'stream' => false,
                'model' => $parametreler['model_adi'] ?? 'fake-model',
            ]);
        }

        return [
            'cevap' => $cevap,
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

function instagramSonMesajAiAyariOlustur(User $aiUser): AiAyar
{
    return AiAyar::create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
        'hafiza_aktif_mi' => true,
        'kisilik_tipi' => 'samimi',
        'konusma_tonu' => 'dogal',
        'konusma_stili' => 'kisa',
        'mesaj_uzunlugu_min' => 10,
        'mesaj_uzunlugu_max' => 120,
    ]);
}

function instagramSonMesajHesabiOlustur(User $aiUser): InstagramHesap
{
    return InstagramHesap::create([
        'user_id' => $aiUser->id,
        'instagram_kullanici_adi' => 'ai.latest',
        'otomatik_cevap_aktif_mi' => true,
        'aktif_mi' => true,
    ]);
}

function instagramSonMesajKisisiOlustur(InstagramHesap $hesap, string $kod = 'chat-1'): InstagramKisi
{
    return InstagramKisi::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kod,
        'kullanici_adi' => 'real.person',
        'gorunen_ad' => 'Real Person',
    ]);
}

it('invalidates older pending instagram ai work when a newer message arrives', function () {
    Queue::fake();

    $aiUser = User::factory()->aiKullanici()->create();
    instagramSonMesajAiAyariOlustur($aiUser);
    $hesap = instagramSonMesajHesabiOlustur($aiUser);
    $kisi = instagramSonMesajKisisiOlustur($hesap);

    $eskiGelenMesaj = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'gonderen_tipi' => 'karsi_taraf',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Ilk mesaj',
    ]);

    $eskiAiMesaji = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'gonderen_tipi' => 'ai',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Eski cevap',
        'gonderildi_mi' => false,
    ]);

    $eskiGorev = InstagramAiGorevi::create([
        'instagram_mesaj_id' => $eskiGelenMesaj->id,
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'durum' => 'yeniden_denecek',
        'deneme_sayisi' => 1,
        'cevap_metni' => 'Eski cevap',
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
    ]);

    Sanctum::actingAs($aiUser);

    $response = $this->postJson("/api/instagram/hesaplar/{$hesap->id}/mesajlar", [
        'mesajlar' => [[
            'instagram_kisi_id' => 'chat-1',
            'gonderen_tipi' => 'karsi_taraf',
            'mesaj_metni' => 'En yeni mesaj',
            'mesaj_tipi' => 'metin',
            'instagram_mesaj_kodu' => 'chat-1|en-yeni-mesaj',
        ]],
    ]);

    $response->assertCreated()
        ->assertJson([
            'kaydedilen_sayisi' => 1,
        ]);

    expect(InstagramMesaj::find($eskiAiMesaji->id))->toBeNull();
    expect($eskiGorev->fresh()->durum)->toBe('gecersiz');
    expect($eskiGelenMesaj->fresh()->ai_cevapladi_mi)->toBeTrue();
    expect(InstagramAiGorevi::query()
        ->where('instagram_mesaj_id', '!=', $eskiGelenMesaj->id)
        ->latest('id')
        ->value('durum'))->toBe('bekliyor');

    Queue::assertPushed(InstagramAiCevapGorevi::class, function (InstagramAiCevapGorevi $job) use ($hesap, $kisi) {
        return $job->hesap->is($hesap)
            && $job->gelenMesaj->instagram_kisi_id === $kisi->id
            && $job->gelenMesaj->mesaj_metni === 'En yeni mesaj'
            && $job->queue === 'ai-stream';
    });
});

it('returns only the latest pending ai reply per instagram person', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hesap = instagramSonMesajHesabiOlustur($aiUser);
    $ilkKisi = instagramSonMesajKisisiOlustur($hesap, 'chat-1');
    $ikinciKisi = instagramSonMesajKisisiOlustur($hesap, 'chat-2');

    $eskiIlkKisiMesaji = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $ilkKisi->id,
        'gonderen_tipi' => 'ai',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Eski ilk cevap',
        'gonderildi_mi' => false,
    ]);

    $yeniIlkKisiMesaji = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $ilkKisi->id,
        'gonderen_tipi' => 'ai',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Yeni ilk cevap',
        'gonderildi_mi' => false,
    ]);

    $ikinciKisiMesaji = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $ikinciKisi->id,
        'gonderen_tipi' => 'ai',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Ikinci kisi cevabi',
        'gonderildi_mi' => false,
    ]);

    InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $ilkKisi->id,
        'gonderen_tipi' => 'ai',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Gonderildi zaten',
        'gonderildi_mi' => true,
    ]);

    Sanctum::actingAs($aiUser);

    $response = $this->getJson("/api/instagram/hesaplar/{$hesap->id}/giden-kuyruk");

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toHaveCount(2);
    expect($ids)->toContain($yeniIlkKisiMesaji->id);
    expect($ids)->toContain($ikinciKisiMesaji->id);
    expect($ids)->not->toContain($eskiIlkKisiMesaji->id);
});

it('marks the instagram ai job invalid without calling the provider when a newer message already exists', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    instagramSonMesajAiAyariOlustur($aiUser);
    $hesap = instagramSonMesajHesabiOlustur($aiUser);
    $kisi = instagramSonMesajKisisiOlustur($hesap);

    $eskiMesaj = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'gonderen_tipi' => 'karsi_taraf',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Ilk mesaj',
    ]);

    InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'gonderen_tipi' => 'karsi_taraf',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Daha yeni mesaj',
    ]);

    $saglayici = new InstagramSonMesajSahteSaglayici();
    $servis = new AiServisi(['gemini' => $saglayici]);

    (new InstagramAiCevapGorevi($eskiMesaj, $hesap))->handle($servis);

    $gorev = InstagramAiGorevi::query()
        ->where('instagram_mesaj_id', $eskiMesaj->id)
        ->first();

    expect($saglayici->cagriSayisi)->toBe(0);
    expect($gorev)->not->toBeNull();
    expect($gorev->durum)->toBe('gecersiz');
    expect(InstagramMesaj::query()->where('gonderen_tipi', 'ai')->count())->toBe(0);
});

it('discards the instagram ai result when a newer message arrives during generation', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    instagramSonMesajAiAyariOlustur($aiUser);
    $hesap = instagramSonMesajHesabiOlustur($aiUser);
    $kisi = instagramSonMesajKisisiOlustur($hesap);

    $eskiMesaj = InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'gonderen_tipi' => 'karsi_taraf',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Ilk mesaj',
    ]);

    $saglayici = new InstagramSonMesajSahteSaglayici(function () use ($hesap, $kisi) {
        InstagramMesaj::create([
            'instagram_hesap_id' => $hesap->id,
            'instagram_kisi_id' => $kisi->id,
            'gonderen_tipi' => 'karsi_taraf',
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => 'Tam cevap gelirken gelen yeni mesaj',
        ]);
    });
    $servis = new AiServisi(['gemini' => $saglayici]);

    (new InstagramAiCevapGorevi($eskiMesaj, $hesap))->handle($servis);

    $gorev = InstagramAiGorevi::query()
        ->where('instagram_mesaj_id', $eskiMesaj->id)
        ->first();

    expect($saglayici->cagriSayisi)->toBe(1);
    expect($gorev)->not->toBeNull();
    expect($gorev->durum)->toBe('gecersiz');
    expect(InstagramMesaj::query()->where('gonderen_tipi', 'ai')->count())->toBe(0);
});
