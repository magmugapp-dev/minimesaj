<?php

namespace Database\Seeders;

use App\Models\AiAyar;
use App\Models\Begeni;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Models\UserFotografi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class GelistirmeSeeder extends Seeder
{
    public function run(): void
    {
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
        $testUser = User::factory()->create([
            'ad' => 'Ayşe',
            'soyad' => 'Demir',
            'kullanici_adi' => 'ayse',
            'email' => 'ayse@minimesaj.test',
            'cinsiyet' => 'kadin',
            'mevcut_puan' => 250,
        ]);

        // ── 3. AI kullanıcıları (5 adet) ────────────────────────────
        $aiKullanicilar = User::factory()
            ->count(5)
            ->aiKullanici()
            ->create();

        // Her AI kullanıcıya ayar oluştur
        foreach ($aiKullanicilar as $ai) {
            AiAyar::create([
                'user_id' => $ai->id,
                'aktif_mi' => true,
                'saglayici_tipi' => 'gemini',
                'model_adi' => 'gemini-2.5-flash',
                'kisilik_tipi' => fake()->randomElement(['sevecen', 'gizemli', 'enerjik', 'entelektüel', 'romantik']),
                'konusma_tonu' => fake()->randomElement(['samimi', 'resmi', 'esprili', 'romantik']),
                'konusma_stili' => fake()->randomElement(['kisa', 'orta', 'uzun']),
                'emoji_seviyesi' => fake()->numberBetween(3, 8),
                'flort_seviyesi' => fake()->numberBetween(4, 9),
                'mizah_seviyesi' => fake()->numberBetween(3, 8),
                'giriskenlik_seviyesi' => fake()->numberBetween(5, 9),
                'mesaj_uzunlugu_min' => 20,
                'mesaj_uzunlugu_max' => 200,
                'temperature' => fake()->randomFloat(2, 0.7, 1.2),
            ]);

            // AI kullanıcılara fotoğraf
            $this->createDevelopmentPhotoIfExists(
                $ai,
                "fotograflar/ai/{$ai->kullanici_adi}_1.jpg",
                1,
                true,
            );
        }

        // ── 4. Normal kullanıcılar (20 adet) ────────────────────────
        $normalKullanicilar = User::factory()->count(20)->create();

        // Her kullanıcıya 1-3 fotoğraf
        foreach ($normalKullanicilar->merge(collect([$testUser])) as $user) {
            $fotoSayisi = fake()->numberBetween(1, 3);
            for ($i = 1; $i <= $fotoSayisi; $i++) {
                $this->createDevelopmentPhotoIfExists(
                    $user,
                    "fotograflar/kullanicilar/{$user->id}_{$i}.jpg",
                    $i,
                    $i === 1,
                );
            }
        }

        // ── 5. Beğeniler + Eşleşmeler ───────────────────────────────
        $tumKullanicilar = $normalKullanicilar->merge($aiKullanicilar)->merge(collect([$testUser]));

        // Test kullanıcısı bazı AI'ları beğensin → eşleşme olsun
        foreach ($aiKullanicilar->take(3) as $ai) {
            // Karşılıklı beğeni
            Begeni::create([
                'begenen_user_id' => $testUser->id,
                'begenilen_user_id' => $ai->id,
                'eslesmeye_donustu_mu' => true,
            ]);
            Begeni::create([
                'begenen_user_id' => $ai->id,
                'begenilen_user_id' => $testUser->id,
                'eslesmeye_donustu_mu' => true,
            ]);

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
        }

        // Normal kullanıcılar arası rastgele beğeniler
        foreach ($normalKullanicilar->take(10) as $user) {
            $hedefler = $tumKullanicilar->where('id', '!=', $user->id)->random(fake()->numberBetween(1, 4));
            foreach ($hedefler as $hedef) {
                Begeni::firstOrCreate([
                    'begenen_user_id' => $user->id,
                    'begenilen_user_id' => $hedef->id,
                ]);
            }
        }

        // ── 6. Premium kullanıcılar (3 adet) ────────────────────────
        User::factory()->count(3)->premium()->create();

        $this->command->info('Geliştirme verileri oluşturuldu: 1 admin, 1 test, 5 AI, 20 normal, 3 premium kullanıcı.');
    }

    private function createDevelopmentPhotoIfExists(User $user, string $path, int $order, bool $isPrimary): void
    {
        if (!Storage::disk('public')->exists($path)) {
            return;
        }

        UserFotografi::create([
            'user_id' => $user->id,
            'dosya_yolu' => $path,
            'sira_no' => $order,
            'ana_fotograf_mi' => $isPrimary,
        ]);

        if ($isPrimary && blank($user->profil_resmi)) {
            $user->forceFill(['profil_resmi' => $path])->save();
        }
    }
}
