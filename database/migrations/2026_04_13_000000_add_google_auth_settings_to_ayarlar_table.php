<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $ayarlar = [
        [
            'anahtar' => 'google_auth_ios_client_id',
            'deger' => '',
            'grup' => 'google_auth',
            'tip' => 'string',
            'aciklama' => 'Google iOS Client ID',
        ],
        [
            'anahtar' => 'google_auth_android_client_id',
            'deger' => '',
            'grup' => 'google_auth',
            'tip' => 'string',
            'aciklama' => 'Google Android Client ID',
        ],
        [
            'anahtar' => 'google_auth_server_client_id',
            'deger' => '',
            'grup' => 'google_auth',
            'tip' => 'string',
            'aciklama' => 'Google Server Client ID',
        ],
    ];

    public function up(): void
    {
        foreach ($this->ayarlar as $ayar) {
            DB::table('ayarlar')->updateOrInsert(
                ['anahtar' => $ayar['anahtar']],
                $ayar,
            );
        }
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->whereIn('anahtar', array_column($this->ayarlar, 'anahtar'))
            ->delete();
    }
};
