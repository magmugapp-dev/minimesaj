<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ayar;
use App\Services\AyarServisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AyarController extends Controller
{
    public function __construct(private AyarServisi $ayarServisi) {}

    /**
     * Dosya yükleme gerektiren ayar anahtarları ve depolama bilgileri.
     */
    private const DOSYA_AYARLARI = [
        'uygulama_logosu' => ['disk' => 'public', 'dizin' => 'ayarlar'],
        'flutter_logosu' => ['disk' => 'public', 'dizin' => 'ayarlar'],
        'apple_private_key_path' => ['disk' => 'local', 'dizin' => 'ayarlar/apple'],
        'google_play_service_account_path' => ['disk' => 'local', 'dizin' => 'ayarlar/google'],
        'firebase_service_account_path' => ['disk' => 'local', 'dizin' => 'ayarlar/firebase'],
    ];

    public function index()
    {
        $gruplar = [
            'genel' => 'Genel',
            'ai_saglayicilar' => 'AI Sağlayıcılar',
            'apple' => 'Apple',
            'google_auth' => 'Google Giris',
            'google_play' => 'Google Play',
            'puan_sistemi' => 'Puan Sistemi',
            'limitler' => 'Limitler',
            'moderasyon' => 'Moderasyon',
            'bildirimler' => 'Bildirimler',
            'eposta' => 'E-posta',
            'guvenlik' => 'Güvenlik',
            'depolama' => 'Depolama',
            'websocket' => 'WebSocket',
            'rate_limiting' => 'Rate Limiting',
            'eslestirme' => 'Eşleştirme',
            'instagram' => 'Instagram',
        ];

        $ayarlar = [];
        foreach (array_keys($gruplar) as $grup) {
            $ayarlar[$grup] = $this->ayarServisi->grupGetir($grup);
        }

        return view('admin.ayarlar.index', compact('gruplar', 'ayarlar'));
    }

    public function guncelle(Request $request)
    {
        $veriler = $request->except(['_token', '_method']);

        // Dosya yüklemelerini işle
        foreach (self::DOSYA_AYARLARI as $anahtar => $yapilandirma) {
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

        return back()->with('basari', 'Ayarlar başarıyla güncellendi.');
    }
}
