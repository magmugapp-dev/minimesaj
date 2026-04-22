<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\InstagramMesaj;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Jobs\InstagramAiCevapGorevi;
use Illuminate\Support\Facades\Bus;

class InstagramAiCevapGoreviTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_cevabi_dbde_gorunur_ve_giden_kuyrukta_gorunur()
    {
        // 1. Hesap ve kişi oluştur
        $hesap = InstagramHesap::factory()->create();
        $kisi = InstagramKisi::factory()->create([
            'instagram_hesap_id' => $hesap->id,
            'instagram_kisi_id' => 'test_kisi_1',
        ]);

        // 2. Karşı taraf mesajı oluştur
        $gelenMesaj = InstagramMesaj::create([
            'instagram_hesap_id' => $hesap->id,
            'instagram_kisi_id' => $kisi->id,
            'gonderen_tipi' => 'karsi_taraf',
            'mesaj_metni' => 'Naber neden cevap vermiyorsun',
            'mesaj_tipi' => 'metin',
            'ai_cevapladi_mi' => false,
            'gonderildi_mi' => false,
            'instagram_mesaj_kodu' => 'test_kisi_1|kod',
        ]);

        // 3. Job'u dispatch et
        Bus::dispatch(new InstagramAiCevapGorevi($gelenMesaj, $hesap));

        // 4. Kuyruktan işlenmesini sağla
        $this->artisan('queue:work --once');

        // 5. AI cevabı DB'de var mı?
        $aiMesaj = InstagramMesaj::where('instagram_hesap_id', $hesap->id)
            ->where('instagram_kisi_id', $kisi->id)
            ->where('gonderen_tipi', 'ai')
            ->where('gonderildi_mi', false)
            ->first();
        $this->assertNotNull($aiMesaj, 'AI cevabı DBde yok!');

        // 6. API ile giden kuyrukta görünüyor mu?
        $response = $this->getJson('/api/hesaplar/' . $hesap->id . '/giden-kuyruk');
        $response->assertStatus(200);
        $this->assertTrue(
            collect($response->json('data'))->contains(function ($mesaj) use ($aiMesaj) {
                return $mesaj['id'] == $aiMesaj->id;
            }),
            'AI cevabı giden kuyrukta görünmüyor!'
        );
    }
}
