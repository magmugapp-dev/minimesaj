<?php

use App\Models\User;

it('renders the redesigned ai studio pages for an admin', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $aiUser = User::factory()->aiKullanici()->create([
        'ad' => 'Aylin',
        'soyad' => 'Deneme',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ai.index'))
        ->assertOk()
        ->assertSeeText('AI Studio')
        ->assertSeeText('Motor ayarlari')
        ->assertSeeText('AI kullanici listesi')
        ->assertSee('placeholder="AI ara"', false)
        ->assertDontSeeText('Motor, persona ve canli akisi tek panelde yonet.');

    $this->actingAs($admin)
        ->get(route('admin.ai.ekle'))
        ->assertOk()
        ->assertSeeText('Yeni AI Persona')
        ->assertSeeText('Kimlik ve Hesap')
        ->assertSeeText('Davranis Sliderlari')
        ->assertSee('Profilde gorunen kisa tanitim metni.', false)
        ->assertSee('Karakterin omurgasini, enerjisini, sosyal tavrini ve birinin aklinda nasil kaldigini yaz.', false)
        ->assertSeeText('Yasam Cevresi')
        ->assertSeeText('Sanat ve kafe mahallesi')
        ->assertSeeText('Kulturel Koken')
        ->assertSeeText('Bati Avrupa')
        ->assertSeeText('Konusma Tonu')
        ->assertSeeText('Utangac ama sicak')
        ->assertSeeText('Cevap Ritmi')
        ->assertSeeText('Yavas ama derinlikli');

    $this->actingAs($admin)
        ->get(route('admin.ai.goster', $aiUser))
        ->assertOk()
        ->assertSeeText('Aylin Deneme')
        ->assertSeeText('Davranis Matrisi');

    $this->actingAs($admin)
        ->get(route('admin.ai.duzenle', $aiUser))
        ->assertOk()
        ->assertSeeText('Davranis Sliderlari')
        ->assertSeeText('Kimlik ve Hesap')
        ->assertSeeText('Kisisel Kurallar');

    $this->actingAs($admin)
        ->get(route('admin.ai.states'))
        ->assertOk()
        ->assertSeeText('Sohbet Durumlari');

    $this->actingAs($admin)
        ->get(route('admin.ai.memories'))
        ->assertOk()
        ->assertSeeText('AI Hafiza');

    $this->actingAs($admin)
        ->get(route('admin.ai.traces'))
        ->assertOk()
        ->assertSeeText('AI Trace');
});
