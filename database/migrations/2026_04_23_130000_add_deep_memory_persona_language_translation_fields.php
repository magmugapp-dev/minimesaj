<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_memories', function (Blueprint $table): void {
            if (!Schema::hasColumn('ai_memories', 'deger')) {
                $table->text('deger')->nullable()->after('icerik');
            }
            if (!Schema::hasColumn('ai_memories', 'normalize_deger')) {
                $table->text('normalize_deger')->nullable()->after('deger');
            }
            if (!Schema::hasColumn('ai_memories', 'guven_skoru')) {
                $table->decimal('guven_skoru', 4, 3)->nullable()->after('normalize_deger');
            }
            if (!Schema::hasColumn('ai_memories', 'gecerlilik_tipi')) {
                $table->string('gecerlilik_tipi', 32)->default('stable')->after('guven_skoru');
            }
            if (!Schema::hasColumn('ai_memories', 'ilk_goruldu_at')) {
                $table->timestamp('ilk_goruldu_at')->nullable()->after('gecerlilik_tipi');
            }
            if (!Schema::hasColumn('ai_memories', 'son_goruldu_at')) {
                $table->timestamp('son_goruldu_at')->nullable()->after('ilk_goruldu_at');
            }
        });

        Schema::table('ai_persona_profiles', function (Blueprint $table): void {
            $this->addColumnIfMissing($table, 'ana_dil_kodu', fn () => $table->string('ana_dil_kodu', 12)->nullable()->after('persona_ozeti'));
            $this->addColumnIfMissing($table, 'ana_dil_adi', fn () => $table->string('ana_dil_adi', 80)->nullable()->after('ana_dil_kodu'));
            $this->addColumnIfMissing($table, 'ikinci_diller', fn () => $table->json('ikinci_diller')->nullable()->after('ana_dil_adi'));
            $this->addColumnIfMissing($table, 'persona_ulke', fn () => $table->string('persona_ulke', 120)->nullable()->after('ikinci_diller'));
            $this->addColumnIfMissing($table, 'persona_bolge', fn () => $table->string('persona_bolge', 120)->nullable()->after('persona_ulke'));
            $this->addColumnIfMissing($table, 'persona_sehir', fn () => $table->string('persona_sehir', 120)->nullable()->after('persona_bolge'));
            $this->addColumnIfMissing($table, 'persona_mahalle', fn () => $table->string('persona_mahalle', 120)->nullable()->after('persona_sehir'));
            $this->addColumnIfMissing($table, 'kulturel_koken', fn () => $table->string('kulturel_koken', 160)->nullable()->after('persona_mahalle'));
            $this->addColumnIfMissing($table, 'uyruk', fn () => $table->string('uyruk', 120)->nullable()->after('kulturel_koken'));
            $this->addColumnIfMissing($table, 'yasam_tarzi', fn () => $table->string('yasam_tarzi', 160)->nullable()->after('uyruk'));
            $this->addColumnIfMissing($table, 'meslek', fn () => $table->string('meslek', 160)->nullable()->after('yasam_tarzi'));
            $this->addColumnIfMissing($table, 'sektor', fn () => $table->string('sektor', 160)->nullable()->after('meslek'));
            $this->addColumnIfMissing($table, 'egitim', fn () => $table->string('egitim', 160)->nullable()->after('sektor'));
            $this->addColumnIfMissing($table, 'okul_bolum', fn () => $table->string('okul_bolum', 220)->nullable()->after('egitim'));
            $this->addColumnIfMissing($table, 'yas_araligi', fn () => $table->string('yas_araligi', 40)->nullable()->after('okul_bolum'));
            $this->addColumnIfMissing($table, 'gunluk_rutin', fn () => $table->text('gunluk_rutin')->nullable()->after('yas_araligi'));
            $this->addColumnIfMissing($table, 'hobiler', fn () => $table->text('hobiler')->nullable()->after('gunluk_rutin'));
            $this->addColumnIfMissing($table, 'sevdigi_mekanlar', fn () => $table->text('sevdigi_mekanlar')->nullable()->after('hobiler'));
            $this->addColumnIfMissing($table, 'aile_arkadas_notu', fn () => $table->text('aile_arkadas_notu')->nullable()->after('sevdigi_mekanlar'));
            $this->addColumnIfMissing($table, 'iliski_gecmisi_tonu', fn () => $table->string('iliski_gecmisi_tonu', 180)->nullable()->after('aile_arkadas_notu'));
            $this->addColumnIfMissing($table, 'konusma_imzasi', fn () => $table->text('konusma_imzasi')->nullable()->after('iliski_gecmisi_tonu'));
            $this->addColumnIfMissing($table, 'argo_seviyesi', fn () => $table->unsignedTinyInteger('argo_seviyesi')->default(2)->after('konusma_imzasi'));
            $this->addColumnIfMissing($table, 'cevap_ritmi', fn () => $table->string('cevap_ritmi', 120)->nullable()->after('argo_seviyesi'));
            $this->addColumnIfMissing($table, 'emoji_aliskanligi', fn () => $table->string('emoji_aliskanligi', 160)->nullable()->after('cevap_ritmi'));
            $this->addColumnIfMissing($table, 'kacinilacak_persona_detaylari', fn () => $table->text('kacinilacak_persona_detaylari')->nullable()->after('emoji_aliskanligi'));
        });

        Schema::table('mesajlar', function (Blueprint $table): void {
            if (!Schema::hasColumn('mesajlar', 'dil_kodu')) {
                $table->string('dil_kodu', 12)->nullable()->after('mesaj_metni');
            }
            if (!Schema::hasColumn('mesajlar', 'dil_adi')) {
                $table->string('dil_adi', 80)->nullable()->after('dil_kodu');
            }
            if (!Schema::hasColumn('mesajlar', 'ceviriler')) {
                $table->json('ceviriler')->nullable()->after('dil_adi');
            }
        });

        DB::table('ai_guardrail_rules')
            ->where('rule_type', 'required_rule')
            ->where('etiket', 'Dil ve ton')
            ->where('icerik', 'like', 'Turkce yaz%')
            ->update([
                'icerik' => 'Persona ana dilinde yaz. Tek mesajda tek niyete odaklan. Gereksiz yapayliktan kac.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('mesajlar', function (Blueprint $table): void {
            foreach (['ceviriler', 'dil_adi', 'dil_kodu'] as $column) {
                if (Schema::hasColumn('mesajlar', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('ai_persona_profiles', function (Blueprint $table): void {
            foreach ([
                'kacinilacak_persona_detaylari',
                'emoji_aliskanligi',
                'cevap_ritmi',
                'argo_seviyesi',
                'konusma_imzasi',
                'iliski_gecmisi_tonu',
                'aile_arkadas_notu',
                'sevdigi_mekanlar',
                'hobiler',
                'gunluk_rutin',
                'yas_araligi',
                'okul_bolum',
                'egitim',
                'sektor',
                'meslek',
                'yasam_tarzi',
                'uyruk',
                'kulturel_koken',
                'persona_mahalle',
                'persona_sehir',
                'persona_bolge',
                'persona_ulke',
                'ikinci_diller',
                'ana_dil_adi',
                'ana_dil_kodu',
            ] as $column) {
                if (Schema::hasColumn('ai_persona_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('ai_memories', function (Blueprint $table): void {
            foreach ([
                'son_goruldu_at',
                'ilk_goruldu_at',
                'gecerlilik_tipi',
                'guven_skoru',
                'normalize_deger',
                'deger',
            ] as $column) {
                if (Schema::hasColumn('ai_memories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addColumnIfMissing(Blueprint $table, string $column, callable $callback): void
    {
        if (!Schema::hasColumn($table->getTable(), $column)) {
            $callback();
        }
    }
};
