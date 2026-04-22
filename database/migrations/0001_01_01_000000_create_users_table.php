<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('ad');
            $table->string('soyad')->nullable();
            $table->string('kullanici_adi')->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('google_kimlik')->nullable()->unique();
            $table->string('apple_kimlik')->nullable()->unique();
            $table->enum('hesap_tipi', ['user', 'ai'])->default('user');
            $table->enum('hesap_durumu', ['aktif', 'pasif', 'yasakli'])->default('aktif');
            $table->integer('dogum_yili')->nullable();
            $table->enum('cinsiyet', ['erkek', 'kadin', 'belirtmek_istemiyorum'])->default('belirtmek_istemiyorum');
            $table->string('ulke')->nullable();
            $table->string('il')->nullable();
            $table->string('ilce')->nullable();
            $table->text('biyografi')->nullable();
            $table->string('profil_resmi')->nullable();
            $table->timestamp('son_gorulme_tarihi')->nullable();
            $table->boolean('cevrim_ici_mi')->default(false);
            $table->boolean('yaziyor_mu')->default(false);
            $table->boolean('ses_acik_mi')->default(true);
            $table->enum('gorunum_modu', ['acik', 'koyu', 'sistem'])->default('sistem');
            $table->boolean('bildirimler_acik_mi')->default(true);
            $table->boolean('titresim_acik_mi')->default(true);
            $table->boolean('premium_aktif_mi')->default(false);
            $table->timestamp('premium_bitis_tarihi')->nullable();
            $table->boolean('profil_one_cikarma_aktif_mi')->default(false);
            $table->integer('mevcut_puan')->default(0);
            $table->integer('gunluk_ucretsiz_hak')->default(3);
            $table->timestamp('son_hak_yenileme_tarihi')->nullable();
            $table->text('cihaz_bilgi')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['hesap_tipi', 'hesap_durumu']);
            $table->index(['cevrim_ici_mi', 'son_gorulme_tarihi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
