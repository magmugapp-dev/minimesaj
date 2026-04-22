<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ayar;
use App\Models\AbonelikPaketi;
use App\Models\PuanPaketi;
use App\Services\AyarServisi;
use App\Services\Odeme\MobilOdemeAyarServisi;
use App\Support\AdminAyarlari;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class AyarController extends Controller
{
    public function __construct(
        private AyarServisi $ayarServisi,
        private MobilOdemeAyarServisi $mobilOdemeAyarServisi,
    ) {}

    /**
     * Dosya yükleme gerektiren ayar anahtarları ve depolama bilgileri.
     */
    private const DOSYA_AYARLARI = [
        'uygulama_logosu' => ['disk' => 'public', 'dizin' => 'ayarlar'],
        'flutter_logosu' => ['disk' => 'public', 'dizin' => 'ayarlar'],
        'apple_private_key_path' => ['disk' => 'local', 'dizin' => 'ayarlar/apple'],
        'apns_sertifika_yolu' => ['disk' => 'local', 'dizin' => 'ayarlar/apple'],
        'google_play_service_account_path' => ['disk' => 'local', 'dizin' => 'ayarlar/google'],
        'firebase_service_account_path' => ['disk' => 'local', 'dizin' => 'ayarlar/firebase'],
    ];

    public function index()
    {
        return redirect()->route('admin.ayarlar.kategori', ['kategori' => 'genel']);
    }

    public function show(string $kategori)
    {
        $kategoriBilgisi = AdminAyarlari::kategori($kategori);

        abort_if($kategoriBilgisi === null, 404);

        $ayarlar = $this->ayarServisi->grupGetir($kategori);
        $kategoriListesi = collect(AdminAyarlari::kategoriler())
            ->map(fn(array $veri, string $slug) => AdminAyarlari::kategori($slug))
            ->filter()
            ->values();

        $ayniGrupKategoriler = $kategoriListesi
            ->filter(fn(array $veri) => $veri['sidebar_grup'] === $kategoriBilgisi['sidebar_grup'])
            ->values();

        $ayarKoleksiyonu = collect($ayarlar);
        $ayarIstatistikleri = [
            'toplam' => $ayarKoleksiyonu->count(),
            'dolu' => $ayarKoleksiyonu->filter(fn(array $ayar) => $this->ayarDoluMu($ayar))->count(),
            'dosya' => $ayarKoleksiyonu->where('tip', 'file')->count(),
            'otomasyon' => $ayarKoleksiyonu->where('tip', 'boolean')->count(),
        ];

        return view('admin.ayarlar.index', [
            'aktifKategori' => $kategori,
            'kategoriBilgisi' => $kategoriBilgisi,
            'ayniGrupKategoriler' => $ayniGrupKategoriler,
            'ayarIstatistikleri' => $ayarIstatistikleri,
            'ayarlar' => $ayarlar,
            'odemeDurumKarti' => $this->odemeDurumKarti($kategori),
        ]);
    }

    public function guncelle(Request $request, string $kategori)
    {
        $kategoriBilgisi = AdminAyarlari::kategori($kategori);

        abort_if($kategoriBilgisi === null, 404);

        $kategoriAnahtarlari = array_keys($this->ayarServisi->grupGetir($kategori));
        $veriler = $request->only($kategoriAnahtarlari);

        // Dosya yüklemelerini işle
        foreach (self::DOSYA_AYARLARI as $anahtar => $yapilandirma) {
            if (!in_array($anahtar, $kategoriAnahtarlari, true)) {
                continue;
            }

            if ($request->hasFile($anahtar)) {
                $dosya = $request->file($anahtar);
                $request->validate([
                    $anahtar => 'file|max:5120', // 5MB
                ]);

                // Eski dosyayı sil
                $eskiDeger = Ayar::where('anahtar', $anahtar)->value('deger');
                if ($eskiDeger && Storage::disk($yapilandirma['disk'])->exists($eskiDeger)) {
                    Storage::disk($yapilandirma['disk'])->delete($eskiDeger);
                }

                $yol = $dosya->store($yapilandirma['dizin'], $yapilandirma['disk']);
                $veriler[$anahtar] = $yol;
            } else {
                // Dosya gönderilmediyse mevcut değeri koru
                unset($veriler[$anahtar]);
            }
        }

        $this->ayarServisi->topluGuncelle($veriler);

        return back()->with('basari', $kategoriBilgisi['etiket'] . ' ayarları başarıyla güncellendi.');
    }

    public function nginxUploadLimitiniUygula(Request $request)
    {
        $request->validate([
            'reload' => 'nullable|boolean',
        ]);

        $exitCode = Artisan::call('storage:sync-nginx-upload-limit', [
            '--apply' => true,
            '--reload' => $request->boolean('reload'),
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return back()->with('hata', 'Nginx upload limiti uygulanamadi. ' . ($output !== '' ? $output : 'Detay icin loglari kontrol et.'));
        }

        return back()->with('basari', 'Nginx upload limiti panel ayariyla senkronlandi. ' . ($output !== '' ? $output : ''));
    }

    private function ayarDoluMu(array $ayar): bool
    {
        $deger = $ayar['deger'] ?? null;

        if ($ayar['tip'] === 'boolean') {
            return true;
        }

        if (is_array($deger)) {
            return !empty($deger);
        }

        if (is_string($deger)) {
            return trim($deger) !== '';
        }

        return $deger !== null;
    }

    private function odemeDurumKarti(string $kategori): ?array
    {
        return match ($kategori) {
            'apple' => $this->appleOdemeDurumKarti(),
            'google_play' => $this->googlePlayOdemeDurumKarti(),
            default => null,
        };
    }

    private function appleOdemeDurumKarti(): array
    {
        $ayarlar = $this->mobilOdemeAyarServisi->appleAyarlari();
        $aktifPuanPaketi = PuanPaketi::query()
            ->where('aktif', true)
            ->whereNotNull('ios_urun_kodu')
            ->where('ios_urun_kodu', '!=', '')
            ->count();
        $aktifAbonelikPaketi = AbonelikPaketi::query()
            ->where('aktif', true)
            ->whereNotNull('ios_urun_kodu')
            ->where('ios_urun_kodu', '!=', '')
            ->count();

        return [
            'platform' => 'App Store',
            'kanal' => 'ios',
            'aktif' => $this->mobilOdemeAyarServisi->kanalAktifMi('ios'),
            'hazir' => $this->mobilOdemeAyarServisi->appleHazirMi(),
            'eksikAlanlar' => array_values(array_filter([
                blank($ayarlar['issuer_id']) ? 'Apple Issuer ID' : null,
                blank($ayarlar['key_id']) ? 'Apple Key ID' : null,
                blank($ayarlar['bundle_id']) ? 'Apple Bundle ID' : null,
                blank($ayarlar['private_key_path']) ? 'Apple Private Key dosyasi' : null,
            ])),
            'paketler' => [
                'puan' => $aktifPuanPaketi,
                'abonelik' => $aktifAbonelikPaketi,
            ],
            'not' => 'iOS satin almalari yalnizca kanal acik ve App Store kimlik alanlari tam ise dogrulanir.',
        ];
    }

    private function googlePlayOdemeDurumKarti(): array
    {
        $ayarlar = $this->mobilOdemeAyarServisi->googlePlayAyarlari();
        $aktifPuanPaketi = PuanPaketi::query()
            ->where('aktif', true)
            ->whereNotNull('android_urun_kodu')
            ->where('android_urun_kodu', '!=', '')
            ->count();
        $aktifAbonelikPaketi = AbonelikPaketi::query()
            ->where('aktif', true)
            ->whereNotNull('android_urun_kodu')
            ->where('android_urun_kodu', '!=', '')
            ->count();

        return [
            'platform' => 'Google Play',
            'kanal' => 'android',
            'aktif' => $this->mobilOdemeAyarServisi->kanalAktifMi('android'),
            'hazir' => $this->mobilOdemeAyarServisi->googlePlayHazirMi(),
            'eksikAlanlar' => array_values(array_filter([
                blank($ayarlar['paket_adi']) ? 'Google Play Paket Adi' : null,
                blank($ayarlar['service_account_path']) ? 'Service Account JSON dosyasi' : null,
            ])),
            'paketler' => [
                'puan' => $aktifPuanPaketi,
                'abonelik' => $aktifAbonelikPaketi,
            ],
            'not' => 'Android satin almalari yalnizca kanal acik ve Google Play servis hesabi tanimli ise dogrulanir.',
        ];
    }
}
