<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Zamanlanmış Görevler
|--------------------------------------------------------------------------
*/

// Horizon snapshot — metrik grafikleri besler
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Cron-only ortamlarda kuyruktaki AI/islem joblarini bosalt
Schedule::command('queue:work --queue=default --stop-when-empty --tries=3 --timeout=330 --memory=256')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->name('default-queue-drain');

// Takilan AI queue/gorev durumlarini toparla
Schedule::command('ai:takilan-gorevleri-kurtar')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('ai-takilan-gorevleri-kurtar');

// Çevrimiçi durumu temizle (30 dk aktif olmayan → çevrimdışı)
Schedule::call(function () {
    DB::table('users')
        ->where('cevrim_ici_mi', true)
        ->where('son_gorulme_tarihi', '<', now()->subMinutes(30))
        ->update(['cevrim_ici_mi' => false]);
})->everyFiveMinutes()->name('cevrimdisi-temizle');

// Günlük istatistik özeti oluştur
Schedule::call(function () {
    $tarih = now()->subDay()->toDateString();
    DB::table('istatistik_ozetleri')->updateOrInsert(
        ['tarih' => $tarih],
        [
            'toplam_kullanici' => DB::table('users')->count(),
            'yeni_kayit' => DB::table('users')->whereDate('created_at', $tarih)->count(),
            'aktif_kullanici' => DB::table('users')->whereDate('son_gorulme_tarihi', $tarih)->count(),
            'toplam_mesaj' => DB::table('mesajlar')->whereDate('created_at', $tarih)->count(),
            'toplam_eslesme' => DB::table('eslesmeler')->whereDate('created_at', $tarih)->count(),
            'toplam_sikayet' => DB::table('sikayetler')->whereDate('created_at', $tarih)->count(),
            'updated_at' => now(),
        ]
    );
})->dailyAt('00:30')->name('gunluk-istatistik');

// Günlük ücretsiz hak yenileme
Schedule::call(function () {
    DB::table('users')
        ->where('hesap_durumu', 'aktif')
        ->where(function ($q) {
            $q->whereNull('son_hak_yenileme_tarihi')
                ->orWhereDate('son_hak_yenileme_tarihi', '<', today());
        })
        ->update([
            'gunluk_ucretsiz_hak' => 3,
            'son_hak_yenileme_tarihi' => now(),
        ]);
})->dailyAt('00:00')->name('gunluk-hak-yenile');

// Premium süresi dolmuş kullanıcıları güncelle
Schedule::call(function () {
    DB::table('users')
        ->where('premium_aktif_mi', true)
        ->where('premium_bitis_tarihi', '<', now())
        ->update([
            'premium_aktif_mi' => false,
            'profil_one_cikarma_aktif_mi' => false,
        ]);
})->hourly()->name('premium-kontrol');

// Başarısız AI görevlerini temizle (7 günden eski)
Schedule::call(function () {
    DB::table('ai_message_turns')
        ->whereIn('status', ['completed', 'cancelled'])
        ->where('updated_at', '<', now()->subMonth())
        ->delete();
})->weekly()->name('ai-turn-temizle');
