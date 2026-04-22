<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCevapBlokaj extends Model
{
    protected $table = 'ai_cevap_blokajlari';
    protected $fillable = [
        'instagram_hesap_id',
        'instagram_kisi_id',
        'blokaj_bitis',
    ];
    public $timestamps = false;
}
