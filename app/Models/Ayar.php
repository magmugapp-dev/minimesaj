<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ayar extends Model
{
    protected $table = 'ayarlar';

    protected $fillable = [
        'anahtar',
        'deger',
        'grup',
        'tip',
        'aciklama',
    ];
}
