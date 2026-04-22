<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $ayarlar = [
        [
            'anahtar' => 'firebase_project_id',
            'deger' => '',
            'grup' => 'bildirimler',
            'tip' => 'string',
            'aciklama' => 'Firebase Project ID',
        ],
        [
            'anahtar' => 'firebase_service_account_path',
            'deger' => '',
            'grup' => 'bildirimler',
            'tip' => 'file',
            'aciklama' => 'Firebase Service Account JSON',
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

        DB::table('ayarlar')
            ->where('anahtar', 'firebase_server_key')
            ->update([
                'aciklama' => 'Firebase Server Key (Legacy - Kullanilmiyor)',
            ]);
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->whereIn('anahtar', array_column($this->ayarlar, 'anahtar'))
            ->delete();

        DB::table('ayarlar')
            ->where('anahtar', 'firebase_server_key')
            ->update([
                'aciklama' => 'Firebase Server Key',
            ]);
    }
};
