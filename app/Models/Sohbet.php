<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sohbet extends Model
{
    protected $table = 'sohbetler';

    protected $fillable = [
        'eslesme_id',
        'son_mesaj_id',
        'son_mesaj_gonderen_user_id',
        'son_mesaj_tarihi',
        'son_mesaj_tipi',
        'son_mesaj_metni',
        'son_mesaj_okundu_mu',
        'ai_durumu',
        'ai_durum_metni',
        'ai_planlanan_cevap_at',
        'ai_durum_guncellendi_at',
        'ai_sessiz_mod_bitis_at',
        'ai_sessiz_mod_tetikleyen_mesaj_id',
        'ai_konusma_kapanisi_at',
        'ai_kapanis_kategorisi',
        'ai_ghost_lockout_until',
        'ai_ghost_tipi',
        'temizlendi_at',
        'toplam_mesaj_sayisi',
        'user_okunmamis_sayisi',
        'eslesen_okunmamis_sayisi',
        'durum',
    ];

    protected function casts(): array
    {
        return [
            'son_mesaj_tarihi' => 'datetime',
            'ai_planlanan_cevap_at' => 'datetime',
            'ai_durum_guncellendi_at' => 'datetime',
            'ai_sessiz_mod_bitis_at' => 'datetime',
            'ai_konusma_kapanisi_at' => 'datetime',
            'ai_ghost_lockout_until' => 'datetime',
            'temizlendi_at' => 'datetime',
            'son_mesaj_okundu_mu' => 'boolean',
        ];
    }

    public function eslesme(): BelongsTo
    {
        return $this->belongsTo(Eslesme::class, 'eslesme_id');
    }

    public function mesajlar(): HasMany
    {
        return $this->hasMany(Mesaj::class, 'sohbet_id');
    }

    public function sonMesaj(): BelongsTo
    {
        return $this->belongsTo(Mesaj::class, 'son_mesaj_id');
    }

    public function aiMessageTurns(): HasMany
    {
        return $this->hasMany(AiMessageTurn::class, 'conversation_id');
    }
}
