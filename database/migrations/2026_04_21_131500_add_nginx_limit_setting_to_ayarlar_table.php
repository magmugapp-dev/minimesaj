<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ayarlar')->updateOrInsert(
            ['anahtar' => 'nginx_max_body_mb'],
            [
                'anahtar' => 'nginx_max_body_mb',
                'deger' => '100',
                'grup' => 'depolama',
                'tip' => 'integer',
                'aciklama' => 'Nginx Maksimum Upload Boyutu (MB)',
            ],
        );
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->where('anahtar', 'nginx_max_body_mb')
            ->delete();
    }
};
