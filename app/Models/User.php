<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'ad',
    'soyad',
    'kullanici_adi',
    'email',
    'password',
    'google_kimlik',
    'apple_kimlik',
    'hesap_tipi',
    'hesap_durumu',
    'is_admin',
    'dogum_yili',
    'cinsiyet',
    'ulke',
    'il',
    'ilce',
    'biyografi',
    'profil_resmi',
    'cevrim_ici_mi',
    'yaziyor_mu',
    'ses_acik_mi',
    'gorunum_modu',
    'bildirimler_acik_mi',
    'titresim_acik_mi',
    'cihaz_bilgi',
    'son_gunluk_giris_puani_tarihi',
    'eslesme_cinsiyet_filtresi',
    'eslesme_yas_filtresi',
    'super_eslesme_aktif_mi',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'son_gorulme_tarihi' => 'datetime',
            'premium_bitis_tarihi' => 'datetime',
            'son_hak_yenileme_tarihi' => 'datetime',
            'son_gunluk_giris_puani_tarihi' => 'datetime',
            'cevrim_ici_mi' => 'boolean',
            'yaziyor_mu' => 'boolean',
            'ses_acik_mi' => 'boolean',
            'bildirimler_acik_mi' => 'boolean',
            'titresim_acik_mi' => 'boolean',
            'premium_aktif_mi' => 'boolean',
            'profil_one_cikarma_aktif_mi' => 'boolean',
            'is_admin' => 'boolean',
            'super_eslesme_aktif_mi' => 'boolean',
        ];
    }

    // ── İlişkiler ────────────────────────────────────────────────────

    public function fotograflar(): HasMany
    {
        return $this->hasMany(UserFotografi::class);
    }

    public function aiAyar(): HasOne
    {
        return $this->hasOne(AiAyar::class);
    }

    public function eslesmeler(): HasMany
    {
        return $this->hasMany(Eslesme::class, 'user_id');
    }

    public function begeniler(): HasMany
    {
        return $this->hasMany(Begeni::class, 'begenen_user_id');
    }

    public function gelenBegeniler(): HasMany
    {
        return $this->hasMany(Begeni::class, 'begenilen_user_id');
    }

    public function engellemeler(): HasMany
    {
        return $this->hasMany(Engelleme::class, 'engelleyen_user_id');
    }

    public function sikayetler(): HasMany
    {
        return $this->hasMany(Sikayet::class, 'sikayet_eden_user_id');
    }

    public function puanHareketleri(): HasMany
    {
        return $this->hasMany(PuanHareketi::class);
    }

    public function odemeler(): HasMany
    {
        return $this->hasMany(Odeme::class);
    }

    public function instagramHesaplari(): HasMany
    {
        return $this->hasMany(InstagramHesap::class);
    }

    public function gonderdigiHediyeler(): HasMany
    {
        return $this->hasMany(HediyeGonderimi::class, 'gonderen_user_id');
    }

    public function aldigiHediyeler(): HasMany
    {
        return $this->hasMany(HediyeGonderimi::class, 'alici_user_id');
    }

    public function pushDeviceTokens(): HasMany
    {
        return $this->hasMany(PushDeviceToken::class);
    }

    public function routeNotificationForFcm(): array
    {
        if (!$this->bildirimler_acik_mi) {
            return [];
        }

        return $this->pushDeviceTokens()
            ->where('bildirim_izni', true)
            ->pluck('token')
            ->all();
    }
}
