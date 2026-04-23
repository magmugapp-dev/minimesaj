<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTurnLog extends Model
{
    protected $fillable = [
        'ai_user_id',
        'kanal',
        'turn_type',
        'hedef_tipi',
        'hedef_id',
        'sohbet_id',
        'gelen_mesaj_id',
        'instagram_hesap_id',
        'instagram_kisi_id',
        'instagram_mesaj_id',
        'durum',
        'yorumlama',
        'cevap_plani',
        'kullanilan_hafiza_idleri',
        'degerlendirme',
        'prompt_ozeti',
        'cevap_metni',
        'ham_cevap',
        'saglayici_tipi',
        'model_adi',
        'giris_token_sayisi',
        'cikis_token_sayisi',
        'yanit_suresi_ms',
        'planlanan_at',
        'baslatildi_at',
        'tamamlandi_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'yorumlama' => 'array',
            'cevap_plani' => 'array',
            'kullanilan_hafiza_idleri' => 'array',
            'degerlendirme' => 'array',
            'metadata' => 'array',
            'planlanan_at' => 'datetime',
            'baslatildi_at' => 'datetime',
            'tamamlandi_at' => 'datetime',
        ];
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }
}
