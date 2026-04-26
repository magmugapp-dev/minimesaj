<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $ayarlar = [
        [
            'anahtar' => 'admob_aktif_mi',
            'deger' => '0',
            'grup' => 'admob',
            'tip' => 'boolean',
            'aciklama' => 'AdMob reklamlari aktif mi',
        ],
        [
            'anahtar' => 'admob_test_modu',
            'deger' => '1',
            'grup' => 'admob',
            'tip' => 'boolean',
            'aciklama' => 'AdMob test modu',
        ],
        [
            'anahtar' => 'admob_android_app_id',
            'deger' => '',
            'grup' => 'admob',
            'tip' => 'string',
            'aciklama' => 'Android AdMob App ID',
        ],
        [
            'anahtar' => 'admob_ios_app_id',
            'deger' => '',
            'grup' => 'admob',
            'tip' => 'string',
            'aciklama' => 'iOS AdMob App ID',
        ],
        [
            'anahtar' => 'admob_android_rewarded_unit_id',
            'deger' => '',
            'grup' => 'admob',
            'tip' => 'string',
            'aciklama' => 'Android odullu reklam birimi',
        ],
        [
            'anahtar' => 'admob_ios_rewarded_unit_id',
            'deger' => '',
            'grup' => 'admob',
            'tip' => 'string',
            'aciklama' => 'iOS odullu reklam birimi',
        ],
        [
            'anahtar' => 'admob_android_match_native_unit_id',
            'deger' => '',
            'grup' => 'admob',
            'tip' => 'string',
            'aciklama' => 'Android eslesme native reklam birimi',
        ],
        [
            'anahtar' => 'admob_ios_match_native_unit_id',
            'deger' => '',
            'grup' => 'admob',
            'tip' => 'string',
            'aciklama' => 'iOS eslesme native reklam birimi',
        ],
        [
            'anahtar' => 'reklam_gunluk_odul_limiti',
            'deger' => '10',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Gunluk reklam odul limiti',
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

        Schema::table('reklam_odulleri', function (Blueprint $table) {
            if (!Schema::hasColumn('reklam_odulleri', 'olay_kodu')) {
                $table->string('olay_kodu')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('reklam_odulleri', 'reklam_platformu')) {
                $table->string('reklam_platformu')->nullable()->after('olay_kodu');
            }

            if (!Schema::hasColumn('reklam_odulleri', 'reklam_birim_kodu')) {
                $table->string('reklam_birim_kodu')->nullable()->after('reklam_platformu');
            }
        });

        Schema::table('reklam_odulleri', function (Blueprint $table) {
            $table->unique('olay_kodu', 'reklam_odulleri_olay_kodu_unique');
            $table->index(['user_id', 'created_at'], 'reklam_odulleri_user_created_index');
        });
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->whereIn('anahtar', array_column($this->ayarlar, 'anahtar'))
            ->delete();

        Schema::table('reklam_odulleri', function (Blueprint $table) {
            $table->dropUnique('reklam_odulleri_olay_kodu_unique');
            $table->dropIndex('reklam_odulleri_user_created_index');
        });

        Schema::table('reklam_odulleri', function (Blueprint $table) {
            if (Schema::hasColumn('reklam_odulleri', 'reklam_birim_kodu')) {
                $table->dropColumn('reklam_birim_kodu');
            }

            if (Schema::hasColumn('reklam_odulleri', 'reklam_platformu')) {
                $table->dropColumn('reklam_platformu');
            }

            if (Schema::hasColumn('reklam_odulleri', 'olay_kodu')) {
                $table->dropColumn('olay_kodu');
            }
        });
    }
};
