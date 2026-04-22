<?php

use App\Models\DestekTalebi;
use App\Models\User;
use App\Notifications\DestekTalebiYanitiOlustu;
use Illuminate\Support\Facades\Notification;

function adminDestekTalebiTestKullanicisi(): User
{
    return User::factory()->create([
        'is_admin' => true,
    ]);
}

it('shows support requests on the admin list page', function () {
    $admin = adminDestekTalebiTestKullanicisi();
    $kullanici = User::factory()->create([
        'ad' => 'Selin',
        'soyad' => 'Korkmaz',
        'email' => 'selin@example.com',
    ]);

    DestekTalebi::create([
        'user_id' => $kullanici->id,
        'mesaj' => 'Profil fotografi yuklerken hata aliyorum ve yardim istiyorum.',
        'durum' => 'yeni',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.moderasyon.destek-talepleri'))
        ->assertOk()
        ->assertSeeText('Selin Korkmaz')
        ->assertSeeText('Profil fotografi yuklerken hata aliyorum');
});

it('shows support request details on the admin detail page', function () {
    $admin = adminDestekTalebiTestKullanicisi();
    $kullanici = User::factory()->create([
        'ad' => 'Can',
        'soyad' => 'Arslan',
        'email' => 'can@example.com',
    ]);

    $talep = DestekTalebi::create([
        'user_id' => $kullanici->id,
        'mesaj' => 'Bildirimler gelmiyor, hesabimi kontrol eder misiniz?',
        'durum' => 'inceleniyor',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.moderasyon.destek-talepleri.goster', $talep))
        ->assertOk()
        ->assertSeeText('Bildirimler gelmiyor, hesabimi kontrol eder misiniz?')
        ->assertSeeText('can@example.com')
        ->assertSee(route('admin.kullanicilar.goster', $kullanici), false);
});

it('updates support request status from the admin detail page', function () {
    $admin = adminDestekTalebiTestKullanicisi();
    $kullanici = User::factory()->create();

    $talep = DestekTalebi::create([
        'user_id' => $kullanici->id,
        'mesaj' => 'Destek durumu guncellenmeli.',
        'durum' => 'yeni',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.moderasyon.destek-talepleri.durum-guncelle', $talep), [
            'durum' => 'cozuldu',
            'yonetici_notu' => 'Kullaniciya geri donus icin kontrol edildi.',
        ])
        ->assertRedirect(route('admin.moderasyon.destek-talepleri.goster', $talep));

    $this->assertDatabaseHas('destek_talepleri', [
        'id' => $talep->id,
        'durum' => 'cozuldu',
        'yonetici_notu' => 'Kullaniciya geri donus icin kontrol edildi.',
    ]);
});

it('shows admin notes and reply history on the support request detail page', function () {
    $admin = adminDestekTalebiTestKullanicisi();
    $kullanici = User::factory()->create([
        'ad' => 'Melis',
        'soyad' => 'Kara',
    ]);

    $talep = DestekTalebi::create([
        'user_id' => $kullanici->id,
        'mesaj' => 'Uygulama acilisinda hata goruyorum.',
        'durum' => 'inceleniyor',
        'yonetici_notu' => 'Log kayitlari istendi.',
    ]);

    $talep->yanitlar()->create([
        'admin_user_id' => $admin->id,
        'mesaj' => 'Kullanicidan ekran kaydi talep edildi.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.moderasyon.destek-talepleri.goster', $talep))
        ->assertOk()
        ->assertSeeText('Log kayitlari istendi.')
        ->assertSeeText('Kullanicidan ekran kaydi talep edildi.')
        ->assertSeeText($admin->ad . ' ' . $admin->soyad);
});

it('adds a reply to the support request history from the admin detail page', function () {
    $admin = adminDestekTalebiTestKullanicisi();
    $kullanici = User::factory()->create();

    $talep = DestekTalebi::create([
        'user_id' => $kullanici->id,
        'mesaj' => 'Yanit kaydi test ediliyor.',
        'durum' => 'yeni',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.moderasyon.destek-talepleri.yanit-ekle', $talep), [
            'mesaj' => 'Sorun yeniden uretilmeye calisiliyor.',
        ])
        ->assertRedirect(route('admin.moderasyon.destek-talepleri.goster', $talep));

    $this->assertDatabaseHas('destek_talebi_yanitlari', [
        'destek_talebi_id' => $talep->id,
        'admin_user_id' => $admin->id,
        'mesaj' => 'Sorun yeniden uretilmeye calisiliyor.',
    ]);
});

it('sends the admin reply to the user by email when requested', function () {
    Notification::fake();

    $admin = adminDestekTalebiTestKullanicisi();
    $kullanici = User::factory()->create([
        'email' => 'destek-kullanici@example.com',
    ]);

    $talep = DestekTalebi::create([
        'user_id' => $kullanici->id,
        'mesaj' => 'Yanitin e-posta ile gonderilmesi test ediliyor.',
        'durum' => 'inceleniyor',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.moderasyon.destek-talepleri.yanit-ekle', $talep), [
            'mesaj' => 'Sorun icin cozum adimlari e-posta ile paylasildi.',
            'kullaniciya_eposta_gonder' => '1',
        ])
        ->assertRedirect(route('admin.moderasyon.destek-talepleri.goster', $talep));

    Notification::assertSentTo(
        $kullanici,
        DestekTalebiYanitiOlustu::class,
        function (DestekTalebiYanitiOlustu $notification, array $channels) use ($talep): bool {
            return in_array('mail', $channels, true)
                && $notification->toMail(new stdClass())->subject === 'MiniMesaj Destek Yanitiniz #' . $talep->id;
        },
    );
});
