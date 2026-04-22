<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('ai_hafizalari_yeni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('hedef_tipi', 32)->default('user');
            $table->unsignedBigInteger('hedef_id');
            $table->foreignId('sohbet_id')->nullable()->constrained('sohbetler')->nullOnDelete();
            $table->enum('hafiza_tipi', ['tercih', 'bilgi', 'duygu', 'ozet', 'sinir'])->default('bilgi');
            $table->string('konu_anahtari', 100);
            $table->text('icerik');
            $table->unsignedTinyInteger('onem_puani')->default(5);
            $table->unsignedBigInteger('kaynak_mesaj_id')->nullable();
            $table->timestamp('son_kullanma_tarihi')->nullable();
            $table->timestamps();

            $table->index(['ai_user_id', 'hedef_tipi', 'hedef_id'], 'ai_hafizalari_hedef_idx');
            $table->unique(
                ['ai_user_id', 'hedef_tipi', 'hedef_id', 'konu_anahtari'],
                'ai_hafizalari_hedef_konu_unique'
            );
        });

        $eskiKayitlar = DB::table('ai_hafizalari')->orderBy('id')->get();

        foreach ($eskiKayitlar as $kayit) {
            DB::table('ai_hafizalari_yeni')->insert([
                'id' => $kayit->id,
                'ai_user_id' => $kayit->ai_user_id,
                'hedef_tipi' => 'user',
                'hedef_id' => $kayit->hedef_user_id,
                'sohbet_id' => $kayit->sohbet_id,
                'hafiza_tipi' => $kayit->hafiza_tipi,
                'konu_anahtari' => 'legacy_' . $kayit->id,
                'icerik' => $kayit->icerik,
                'onem_puani' => $kayit->onem_puani,
                'kaynak_mesaj_id' => null,
                'son_kullanma_tarihi' => $kayit->son_kullanma_tarihi,
                'created_at' => $kayit->created_at,
                'updated_at' => $kayit->updated_at,
            ]);
        }

        Schema::drop('ai_hafizalari');
        Schema::rename('ai_hafizalari_yeni', 'ai_hafizalari');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('ai_hafizalari_eski', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hedef_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sohbet_id')->nullable()->constrained('sohbetler')->nullOnDelete();
            $table->enum('hafiza_tipi', ['tercih', 'bilgi', 'duygu', 'ozet', 'sinir'])->default('bilgi');
            $table->text('icerik');
            $table->unsignedTinyInteger('onem_puani')->default(5);
            $table->timestamp('son_kullanma_tarihi')->nullable();
            $table->timestamps();
        });

        $kayitlar = DB::table('ai_hafizalari')
            ->where('hedef_tipi', 'user')
            ->orderBy('id')
            ->get();

        foreach ($kayitlar as $kayit) {
            DB::table('ai_hafizalari_eski')->insert([
                'id' => $kayit->id,
                'ai_user_id' => $kayit->ai_user_id,
                'hedef_user_id' => $kayit->hedef_id,
                'sohbet_id' => $kayit->sohbet_id,
                'hafiza_tipi' => $kayit->hafiza_tipi,
                'icerik' => $kayit->icerik,
                'onem_puani' => $kayit->onem_puani,
                'son_kullanma_tarihi' => $kayit->son_kullanma_tarihi,
                'created_at' => $kayit->created_at,
                'updated_at' => $kayit->updated_at,
            ]);
        }

        Schema::drop('ai_hafizalari');
        Schema::rename('ai_hafizalari_eski', 'ai_hafizalari');

        Schema::enableForeignKeyConstraints();
    }
};
