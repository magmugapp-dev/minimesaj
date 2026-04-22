<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite enum desteği olmadığı için sütunu yeniden oluştur
        if (DB::getDriverName() === 'sqlite') {
            // SQLite'ta enum constraint'i PRAGMA ile değiştirilemez,
            // tip sütununu string olarak güncelle (check constraint kaldırılır)
            DB::statement('PRAGMA foreign_keys=off');
            DB::statement('CREATE TABLE ayarlar_yedek AS SELECT * FROM ayarlar');
            DB::statement('DROP TABLE ayarlar');
            DB::statement("
                CREATE TABLE ayarlar (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    anahtar VARCHAR NOT NULL UNIQUE,
                    deger TEXT,
                    grup VARCHAR NOT NULL,
                    tip VARCHAR NOT NULL DEFAULT 'string' CHECK(tip IN ('string','integer','boolean','json','text','file')),
                    aciklama VARCHAR,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
            DB::statement('CREATE INDEX ayarlar_grup_index ON ayarlar(grup)');
            DB::statement('INSERT INTO ayarlar SELECT * FROM ayarlar_yedek');
            DB::statement('DROP TABLE ayarlar_yedek');
            DB::statement('PRAGMA foreign_keys=on');
        } else {
            // MySQL: enum'a file ekle
            DB::statement("ALTER TABLE ayarlar MODIFY COLUMN tip ENUM('string','integer','boolean','json','text','file') DEFAULT 'string'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');
            DB::statement('CREATE TABLE ayarlar_yedek AS SELECT * FROM ayarlar');
            DB::statement('DROP TABLE ayarlar');
            DB::statement("
                CREATE TABLE ayarlar (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    anahtar VARCHAR NOT NULL UNIQUE,
                    deger TEXT,
                    grup VARCHAR NOT NULL,
                    tip VARCHAR NOT NULL DEFAULT 'string' CHECK(tip IN ('string','integer','boolean','json','text')),
                    aciklama VARCHAR,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
            DB::statement('CREATE INDEX ayarlar_grup_index ON ayarlar(grup)');
            DB::statement("INSERT INTO ayarlar SELECT * FROM ayarlar_yedek WHERE tip != 'file'");
            DB::statement('DROP TABLE ayarlar_yedek');
            DB::statement('PRAGMA foreign_keys=on');
        } else {
            DB::statement("ALTER TABLE ayarlar MODIFY COLUMN tip ENUM('string','integer','boolean','json','text') DEFAULT 'string'");
        }
    }
};
