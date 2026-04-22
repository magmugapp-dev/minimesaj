<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiHafiza extends Model
{
    public const HEDEF_TIPI_USER = 'user';
    public const HEDEF_TIPI_INSTAGRAM_KISI = 'instagram_kisi';

    public const HAFIZA_TIPI_TERCIH = 'tercih';
    public const HAFIZA_TIPI_BILGI = 'bilgi';
    public const HAFIZA_TIPI_DUYGU = 'duygu';
    public const HAFIZA_TIPI_OZET = 'ozet';
    public const HAFIZA_TIPI_SINIR = 'sinir';

    public const DUYGU_TTL_GUN = 3;

    protected $table = 'ai_hafizalari';

    protected $fillable = [
        'ai_user_id',
        'hedef_tipi',
        'hedef_id',
        'sohbet_id',
        'hafiza_tipi',
        'konu_anahtari',
        'icerik',
        'onem_puani',
        'kaynak_mesaj_id',
        'son_kullanma_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'son_kullanma_tarihi' => 'datetime',
        ];
    }

    public static function izinliKonuTanimlari(): array
    {
        return [
            'ad_soyad' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Kisinin adini veya ad soyadini belirtir.',
            ],
            'memleket' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Aslen nereli oldugunu veya memleketini belirtir.',
            ],
            'yasadigi_sehir' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Su an yasadigi sehri veya ilini belirtir.',
            ],
            'meslek' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Meslegi, isi veya calisma alani.',
            ],
            'egitim_lise_durum' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Lise ile ilgili durum: mezun, terk, devam ediyor gibi.',
            ],
            'egitim_universite_adi' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Universite veya yuksekokul adi.',
            ],
            'egitim_universite_bolum' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Universitede okudugu veya mezun oldugu bolum.',
            ],
            'egitim_universite_durum' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_BILGI,
                'aciklama' => 'Universite ile ilgili durum: mezun, ogrenci, birakti gibi.',
            ],
            'tercih_genel' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_TERCIH,
                'aciklama' => 'Genel bir kisisel tercih.',
            ],
            'tercih_yemek' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_TERCIH,
                'aciklama' => 'Sevdigi veya sevmedigi yemek tercihi.',
            ],
            'tercih_icecek' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_TERCIH,
                'aciklama' => 'Sevdigi veya sevmedigi icecek tercihi.',
            ],
            'tercih_hobi' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_TERCIH,
                'aciklama' => 'Sevdigi aktivite, hobi veya bos zaman tercihi.',
            ],
            'tercih_iletisim' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_TERCIH,
                'aciklama' => 'Yazisma, arama veya iletisim sekli tercihi.',
            ],
            'tercih_bulusma' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_TERCIH,
                'aciklama' => 'Bulusma veya sosyallesme tercihi.',
            ],
            'sinir_genel' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_SINIR,
                'aciklama' => 'Genel bir hassasiyet veya sinir.',
            ],
            'sinir_konu' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_SINIR,
                'aciklama' => 'Konusmak istemedigi konu veya tetikleyici alan.',
            ],
            'sinir_iletisim' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_SINIR,
                'aciklama' => 'Iletisim tarziyla ilgili net sinir veya kural.',
            ],
            'sinir_iliski' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_SINIR,
                'aciklama' => 'Iliski veya flort sureciyle ilgili belirgin sinir.',
            ],
            'duygu_genel' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_DUYGU,
                'ttl_gun' => self::DUYGU_TTL_GUN,
                'aciklama' => 'O anki genel duygu durumu.',
            ],
            'duygu_iliskiye_karsi' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_DUYGU,
                'ttl_gun' => self::DUYGU_TTL_GUN,
                'aciklama' => 'Size veya iliskiye karsi anlik duygusu.',
            ],
            'duygu_gunluk_stres' => [
                'hafiza_tipi' => self::HAFIZA_TIPI_DUYGU,
                'ttl_gun' => self::DUYGU_TTL_GUN,
                'aciklama' => 'Gunun stresi, yorgunluk veya moral seviyesi gibi kisa omurlu durum.',
            ],
        ];
    }

    public static function konuTanimi(?string $konuAnahtari): ?array
    {
        if (!$konuAnahtari) {
            return null;
        }

        return self::izinliKonuTanimlari()[$konuAnahtari] ?? null;
    }

    public static function izinliKonuAnahtarlari(): array
    {
        return array_keys(self::izinliKonuTanimlari());
    }

    public function scopeHedef(Builder $query, string $hedefTipi, int $hedefId): Builder
    {
        return $query
            ->where('hedef_tipi', $hedefTipi)
            ->where('hedef_id', $hedefId);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where(function (Builder $sorgu) {
            $sorgu->whereNull('son_kullanma_tarihi')
                ->orWhere('son_kullanma_tarihi', '>', now());
        });
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }

    public function sohbet(): BelongsTo
    {
        return $this->belongsTo(Sohbet::class, 'sohbet_id');
    }
}
