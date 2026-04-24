<?php

namespace Database\Seeders;

use App\Models\AiAyar;
use App\Models\Ayar;
use App\Models\AbonelikPaketi;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Odeme;
use App\Models\PuanHareketi;
use App\Models\PuanPaketi;
use App\Models\Sohbet;
use App\Models\User;
use App\Models\UserFotografi;
use App\Notifications\YeniEslesme;
use App\Notifications\YeniMesaj;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Support\MediaUrl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;

class GelistirmeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBildirimAyarlari();

        // ── 1. Admin kullanıcı (AdminSeeder tarafından oluşturuldu) ──
        $admin = User::where('is_admin', true)->first()
            ?? User::factory()->create([
                'ad' => 'Admin',
                'soyad' => 'Yetki',
                'kullanici_adi' => 'admin',
                'email' => 'admin@minimesaj.test',
                'hesap_tipi' => 'user',
                'cinsiyet' => 'erkek',
                'mevcut_puan' => 9999,
                'is_admin' => true,
            ]);

        // ── 2. Ana test kullanıcısı ──────────────────────────────────
        $testUser = User::query()->updateOrCreate(
            ['email' => 'ayse@minimesaj.test'],
            [
                'ad' => 'Ayşe',
                'soyad' => 'Demir',
                'kullanici_adi' => 'ayse',
                'cinsiyet' => 'kadin',
                'hesap_tipi' => 'user',
                'hesap_durumu' => 'aktif',
                'dil' => 'tr',
                'bildirimler_acik_mi' => true,
                'titresim_acik_mi' => true,
                'mevcut_puan' => 250,
            ],
        );

        // ── 3. AI kullanıcıları (5 adet) ────────────────────────────
        $aiKullanicilar = User::factory()
            ->count(5)
            ->aiKullanici()
            ->create();

        // Her AI kullanıcıya ayar oluştur
        foreach ($aiKullanicilar as $ai) {
            $kisilik = fake()->randomElement([
                'sevecen',
                'gizemli',
                'enerjik',
                'entelektüel',
                'romantik',
            ]);
            $ton = fake()->randomElement(['samimi', 'resmi', 'esprili', 'romantik']);
            $stil = fake()->randomElement(['kisa', 'orta', 'uzun']);

            AiAyar::updateOrCreate([
                'user_id' => $ai->id,
            ], [
                'aktif_mi' => true,
                'saglayici_tipi' => 'gemini',
                'model_adi' => GeminiSaglayici::MODEL_ADI,
                'yedek_saglayici_tipi' => 'openai',
                'yedek_model_adi' => 'gpt-4.1-mini',
                'kisilik_tipi' => $kisilik,
                'kisilik_aciklamasi' => fake('tr_TR')->sentence(18),
                'konusma_tonu' => $ton,
                'konusma_stili' => $stil,
                'emoji_seviyesi' => fake()->numberBetween(3, 8),
                'flort_seviyesi' => fake()->numberBetween(4, 9),
                'giriskenlik_seviyesi' => fake()->numberBetween(5, 9),
                'utangaclik_seviyesi' => fake()->numberBetween(2, 7),
                'duygusallik_seviyesi' => fake()->numberBetween(4, 8),
                'kiskanclik_seviyesi' => fake()->numberBetween(1, 6),
                'mizah_seviyesi' => fake()->numberBetween(3, 8),
                'zeka_seviyesi' => fake()->numberBetween(5, 9),
                'ilk_mesaj_atar_mi' => fake()->boolean(65),
                'ilk_mesaj_sablonu' => fake('tr_TR')->sentence(10),
                'gunluk_konusma_limiti' => fake()->numberBetween(80, 180),
                'tek_kullanici_gunluk_mesaj_limiti' => fake()->numberBetween(20, 60),
                'minimum_cevap_suresi_saniye' => fake()->numberBetween(5, 25),
                'maksimum_cevap_suresi_saniye' => fake()->numberBetween(30, 120),
                'ortalama_mesaj_uzunlugu' => fake()->numberBetween(45, 120),
                'mesaj_uzunlugu_min' => 20,
                'mesaj_uzunlugu_max' => 200,
                'sesli_mesaj_gonderebilir_mi' => fake()->boolean(30),
                'foto_gonderebilir_mi' => fake()->boolean(45),
                'saat_dilimi' => 'Europe/Istanbul',
                'uyku_baslangic' => fake()->randomElement(['22:30', '23:00', '23:30']),
                'uyku_bitis' => fake()->randomElement(['07:00', '07:30', '08:00']),
                'hafta_sonu_uyku_baslangic' => fake()->randomElement(['00:00', '00:30', '01:00']),
                'hafta_sonu_uyku_bitis' => fake()->randomElement(['09:00', '09:30', '10:00']),
                'rastgele_gecikme_dakika' => fake()->numberBetween(5, 25),
                'sistem_komutu' => fake('tr_TR')->paragraph(3),
                'yasakli_konular' => ['nefret soylemi', 'kisisel veri isteme'],
                'zorunlu_kurallar' => ['nazik ol', 'kisa cevap ver', 'guvenli sinirlarda kal'],
                'hafiza_aktif_mi' => true,
                'hafiza_seviyesi' => fake()->randomElement(['dusuk', 'orta', 'yuksek']),
                'kullaniciyi_hatirlar_mi' => true,
                'iliski_seviyesi_takibi_aktif_mi' => true,
                'puanlama_etiketi' => fake()->randomElement(['sicak', 'flortoz', 'dengeli']),
                'temperature' => fake()->randomFloat(2, 0.70, 1.20),
                'top_p' => fake()->randomFloat(2, 0.80, 1.00),
                'max_output_tokens' => fake()->numberBetween(256, 1024),
            ]);

            $this->seedUserPhotos($ai, 1);
        }

        // ── 4. Normal kullanıcılar (20 adet) ────────────────────────
        $normalKullanicilar = User::factory()->count(20)->create();

        // Her kullanıcıya 1-3 fotoğraf
        foreach ($normalKullanicilar->merge(collect([$testUser])) as $user) {
            $this->seedUserPhotos($user, fake()->numberBetween(1, 3));
        }

        // ---- 5. Eslesmeler ve sohbetler ------------------------------------------------
        $olusanEslesmeler = collect();
        $olusanMesajlar = collect();

        foreach ($aiKullanicilar->take(3) as $ai) {
            // Eşleşme + Sohbet
            $eslesme = Eslesme::create([
                'user_id' => $testUser->id,
                'eslesen_user_id' => $ai->id,
                'eslesme_turu' => 'otomatik',
                'eslesme_kaynagi' => 'yapay_zeka',
                'durum' => 'aktif',
                'baslatan_user_id' => $testUser->id,
            ]);

            $sohbet = Sohbet::create([
                'eslesme_id' => $eslesme->id,
                'durum' => 'aktif',
            ]);

            // Örnek mesajlar
            $mesajSayisi = fake()->numberBetween(3, 10);
            $sonMesaj = null;
            for ($i = 0; $i < $mesajSayisi; $i++) {
                $gonderen = $i % 2 === 0 ? $testUser : $ai;
                $sonMesaj = Mesaj::create([
                    'sohbet_id' => $sohbet->id,
                    'gonderen_user_id' => $gonderen->id,
                    'mesaj_tipi' => 'metin',
                    'mesaj_metni' => fake('tr_TR')->realText(fake()->numberBetween(20, 150)),
                    'okundu_mu' => true,
                    'ai_tarafindan_uretildi_mi' => $gonderen->hesap_tipi === 'ai',
                ]);
            }

            $sohbet->update([
                'son_mesaj_id' => $sonMesaj?->id,
                'son_mesaj_tarihi' => $sonMesaj?->created_at,
                'toplam_mesaj_sayisi' => $mesajSayisi,
            ]);

            $olusanEslesmeler->push($eslesme);
            if ($sonMesaj !== null) {
                $olusanMesajlar->push($sonMesaj);
            }
        }

        // ── 6. Premium kullanıcılar (3 adet) ────────────────────────
        User::factory()->count(3)->premium()->create();

        $this->seedCuzdanVeOdemeVerileri($testUser);

        if ($aiKullanicilar->isNotEmpty()) {
            $this->seedBildirimVerileri(
                $testUser,
                $aiKullanicilar->first(),
                $olusanEslesmeler->first(),
                $olusanMesajlar->first(),
            );
        }

        $this->command->info('Geliştirme verileri oluşturuldu: 1 admin, 1 test, 5 AI, 20 normal, 3 premium kullanıcı.');
    }

    private function seedBildirimAyarlari(): void
    {
        $firebaseDosyalari = collect(Storage::disk('local')->files('ayarlar/firebase'))
            ->filter(fn(string $path) => str_ends_with(strtolower($path), '.json'))
            ->values();

        if ($firebaseDosyalari->isEmpty()) {
            return;
        }

        $secilenDosya = $firebaseDosyalari
            ->first(fn(string $path) => str_contains(strtolower($path), 'firebase-adminsdk'))
            ?? $firebaseDosyalari->first();

        if (!is_string($secilenDosya) || $secilenDosya === '') {
            return;
        }

        $mevcutServisHesabi = Ayar::query()->where('anahtar', 'firebase_service_account_path')->value('deger');
        if (!is_string($mevcutServisHesabi) || trim($mevcutServisHesabi) === '') {
            Ayar::query()->updateOrCreate(
                ['anahtar' => 'firebase_service_account_path'],
                [
                    'deger' => $secilenDosya,
                    'grup' => 'bildirimler',
                    'tip' => 'file',
                    'aciklama' => 'Firebase Service Account JSON',
                ],
            );
        }

        Cache::forget('ayar:firebase_service_account_path');

        $mevcutProjectId = Ayar::query()->where('anahtar', 'firebase_project_id')->value('deger');
        if (is_string($mevcutProjectId) && trim($mevcutProjectId) !== '') {
            Cache::forget('ayar:firebase_project_id');
            return;
        }

        try {
            $icerik = json_decode(
                (string) Storage::disk('local')->get($secilenDosya),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return;
        }

        $projectId = is_array($icerik) ? ($icerik['project_id'] ?? null) : null;
        if (!is_string($projectId) || trim($projectId) === '') {
            return;
        }

        Ayar::query()->updateOrCreate(
            ['anahtar' => 'firebase_project_id'],
            [
                'deger' => trim($projectId),
                'grup' => 'bildirimler',
                'tip' => 'string',
                'aciklama' => 'Firebase Project ID',
            ],
        );

        Cache::forget('ayar:firebase_project_id');
    }

    private function seedUserPhotos(User $user, int $desiredCount): void
    {
        $samplePaths = $this->sampleImagePaths();

        if ($samplePaths->isEmpty()) {
            return;
        }

        $selectedSamples = $samplePaths->shuffle()->take(max(1, $desiredCount))->values();
        $primaryPath = null;

        foreach ($selectedSamples as $index => $sourcePath) {
            $order = $index + 1;
            $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
            $destinationPath = "fotograflar/{$user->id}/seed_{$order}.{$extension}";

            if (!Storage::disk('public')->exists($destinationPath) && $sourcePath !== $destinationPath) {
                Storage::disk('public')->copy($sourcePath, $destinationPath);
            }

            UserFotografi::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'sira_no' => $order,
                ],
                [
                    'dosya_yolu' => $destinationPath,
                    'medya_tipi' => 'fotograf',
                    'mime_tipi' => $this->mimeFromExtension($extension),
                    'ana_fotograf_mi' => $order === 1,
                    'aktif_mi' => true,
                ],
            );

            if ($order === 1) {
                $primaryPath = $destinationPath;
            }
        }

        if ($primaryPath !== null) {
            $user->forceFill(['profil_resmi' => $primaryPath])->save();
        }
    }

    private function sampleImagePaths(): Collection
    {
        static $paths;

        if ($paths instanceof Collection) {
            return $paths;
        }

        $paths = collect(Storage::disk('public')->allFiles('fotograflar'))
            ->filter(fn(string $path) => preg_match('/\.(jpg|jpeg|png|webp)$/i', $path) === 1)
            ->values();

        return $paths;
    }

    private function mimeFromExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpeg', 'jpg' => 'image/jpeg',
            default => 'image/jpeg',
        };
    }

    private function seedCuzdanVeOdemeVerileri(User $testUser): void
    {
        $creditPackage = PuanPaketi::query()->where('kod', 'kredi_60')->first();
        $subscriptionPackage = AbonelikPaketi::query()->where('kod', 'premium_1_ay')->first();

        $creditPayment = Odeme::query()->updateOrCreate(
            ['islem_kodu' => 'seed-google-credit-ayse'],
            [
                'user_id' => $testUser->id,
                'platform' => 'android',
                'magaza_tipi' => 'google_play',
                'urun_kodu' => $creditPackage?->android_urun_kodu ?? 'kredi_60',
                'urun_tipi' => 'tek_seferlik',
                'tutar' => $creditPackage?->fiyat ?? 99.99,
                'para_birimi' => $creditPackage?->para_birimi ?? 'TRY',
                'durum' => 'tamamlandi',
                'dogrulama_durumu' => 'dogrulandi',
            ],
        );

        Odeme::query()->updateOrCreate(
            ['islem_kodu' => 'seed-ios-premium-ayse'],
            [
                'user_id' => $testUser->id,
                'platform' => 'ios',
                'magaza_tipi' => 'app_store',
                'urun_kodu' => $subscriptionPackage?->ios_urun_kodu ?? 'premium_1_ay',
                'urun_tipi' => 'abonelik',
                'tutar' => $subscriptionPackage?->fiyat ?? 3.99,
                'para_birimi' => $subscriptionPackage?->para_birimi ?? 'USD',
                'durum' => 'tamamlandi',
                'dogrulama_durumu' => 'dogrulandi',
            ],
        );

        $hareketler = [
            [
                'anahtar' => 'seed-register-bonus-ayse',
                'islem_tipi' => 'yonetici',
                'puan_miktari' => 100,
                'onceki_bakiye' => 0,
                'sonraki_bakiye' => 100,
                'aciklama' => 'Kayit bonusu',
                'referans_tipi' => null,
                'referans_id' => null,
            ],
            [
                'anahtar' => 'seed-credit-purchase-ayse',
                'islem_tipi' => 'odeme',
                'puan_miktari' => 60,
                'onceki_bakiye' => 100,
                'sonraki_bakiye' => 160,
                'aciklama' => 'Kredi paketi satin alma',
                'referans_tipi' => Odeme::class,
                'referans_id' => $creditPayment->id,
            ],
            [
                'anahtar' => 'seed-match-cost-ayse',
                'islem_tipi' => 'harcama',
                'puan_miktari' => -8,
                'onceki_bakiye' => 160,
                'sonraki_bakiye' => 152,
                'aciklama' => 'Eslesme baslatma maliyeti',
                'referans_tipi' => 'eslesme',
                'referans_id' => null,
            ],
            [
                'anahtar' => 'seed-ad-reward-ayse',
                'islem_tipi' => 'reklam',
                'puan_miktari' => 15,
                'onceki_bakiye' => 152,
                'sonraki_bakiye' => 167,
                'aciklama' => 'Reklam izleme odulu',
                'referans_tipi' => 'reklam',
                'referans_id' => null,
            ],
        ];

        foreach ($hareketler as $hareket) {
            PuanHareketi::query()->updateOrCreate(
                [
                    'user_id' => $testUser->id,
                    'aciklama' => $hareket['aciklama'],
                ],
                [
                    'islem_tipi' => $hareket['islem_tipi'],
                    'puan_miktari' => $hareket['puan_miktari'],
                    'onceki_bakiye' => $hareket['onceki_bakiye'],
                    'sonraki_bakiye' => $hareket['sonraki_bakiye'],
                    'referans_tipi' => $hareket['referans_tipi'],
                    'referans_id' => $hareket['referans_id'],
                ],
            );
        }

        $testUser->forceFill([
            'mevcut_puan' => 167,
            'premium_aktif_mi' => true,
            'premium_bitis_tarihi' => now()->addDays(25),
            'gunluk_ucretsiz_hak' => 2,
        ])->save();
    }

    private function seedBildirimVerileri(
        User $testUser,
        User $kaynakKullanici,
        ?Eslesme $eslesme,
        ?Mesaj $mesaj,
    ): void {
        $bildirimler = [];

        if ($eslesme !== null) {
            $bildirimler[] = [
                'id' => '0ed4b5e0-4148-4bd7-9a88-4fa360a99402',
                'type' => YeniEslesme::class,
                'data' => (new YeniEslesme($eslesme, $kaynakKullanici))->toArray($testUser),
                'created_at' => now()->subHours(3),
                'read_at' => null,
            ];
        }

        if ($mesaj !== null) {
            $bildirimler[] = [
                'id' => '0ed4b5e0-4148-4bd7-9a88-4fa360a99403',
                'type' => YeniMesaj::class,
                'data' => (new YeniMesaj($mesaj, $kaynakKullanici))->toArray($testUser),
                'created_at' => now()->subHour(),
                'read_at' => null,
            ];
        }

        $bildirimler[] = [
            'id' => '0ed4b5e0-4148-4bd7-9a88-4fa360a99404',
            'type' => 'App\\Notifications\\WalletPromo',
            'data' => [
                'tip' => 'cuzdan_firsati',
                'baslik' => 'Kredi firsati',
                'govde' => 'Bugun kredi satin alirsan ekstra tas kazanabilirsin.',
                'mesaj' => 'Bugun kredi satin alirsan ekstra tas kazanabilirsin.',
                'rota' => 'wallet',
                'rota_parametreleri' => [],
                'profil_resmi' => MediaUrl::resolve($kaynakKullanici->profil_resmi),
            ],
            'created_at' => now()->subMinutes(30),
            'read_at' => now()->subMinutes(10),
        ];

        foreach ($bildirimler as $bildirim) {
            DB::table('notifications')->updateOrInsert(
                ['id' => $bildirim['id']],
                [
                    'type' => $bildirim['type'],
                    'notifiable_type' => User::class,
                    'notifiable_id' => $testUser->id,
                    'data' => json_encode($bildirim['data'], JSON_UNESCAPED_UNICODE),
                    'read_at' => $bildirim['read_at'],
                    'created_at' => $bildirim['created_at'],
                    'updated_at' => $bildirim['created_at'],
                ],
            );
        }
    }
}
