<?php

use App\Models\InstagramHesap;
use App\Models\User;

it('renders the influencer admin pages with the studio form', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $influencer = User::factory()->aiKullanici()->create([
        'ad' => 'Burcin',
        'soyad' => 'Evci',
        'kullanici_adi' => 'burcin_ui_influencer',
    ]);

    InstagramHesap::factory()->create([
        'user_id' => $influencer->id,
        'instagram_kullanici_adi' => 'burcinprofile',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.influencer.index'))
        ->assertOk()
        ->assertSeeText('JSON Import')
        ->assertSeeText('Yeni Influencer Ekle');

    $this->actingAs($admin)
        ->get(route('admin.influencer.ekle'))
        ->assertOk()
        ->assertSeeText('Yeni Influencer Persona')
        ->assertSeeText('Instagram Operasyonu')
        ->assertSeeText('Instagram Hesabi Aktif')
        ->assertSeeText('Aktif / Pasif Saatler');

    $this->actingAs($admin)
        ->get(route('admin.influencer.duzenle', $influencer))
        ->assertOk()
        ->assertSeeText('Instagram Operasyonu')
        ->assertSeeText('Kimlik ve Hesap')
        ->assertSeeText('Kisisel Kurallar')
        ->assertSeeText('Aktif / Pasif Saatler');

    $this->actingAs($admin)
        ->get(route('admin.influencer.goster', $influencer))
        ->assertOk()
        ->assertSeeText('Sil')
        ->assertSeeText('Instagram');
});
