<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_engine_configs', function (Blueprint $table) {
            $table->id();
            $table->string('ad')->default('Varsayilan Motor');
            $table->string('saglayici_tipi')->default('gemini');
            $table->string('model_adi')->default('gemini-2.5-flash');
            $table->boolean('aktif_mi')->default(true);
            $table->decimal('temperature', 4, 2)->default(0.9);
            $table->decimal('top_p', 4, 2)->default(0.95);
            $table->unsignedInteger('max_output_tokens')->default(1024);
            $table->text('sistem_komutu')->nullable();
            $table->string('guardrail_modu')->default('strict');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_persona_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('ai_engine_config_id')->nullable()->constrained('ai_engine_configs')->nullOnDelete();
            $table->boolean('aktif_mi')->default(true);
            $table->boolean('dating_aktif_mi')->default(true);
            $table->boolean('instagram_aktif_mi')->default(true);
            $table->boolean('ilk_mesaj_atar_mi')->default(true);
            $table->text('ilk_mesaj_tonu')->nullable();
            $table->text('persona_ozeti')->nullable();
            $table->string('konusma_tonu')->nullable();
            $table->string('konusma_stili')->nullable();
            $table->unsignedTinyInteger('mizah_seviyesi')->default(5);
            $table->unsignedTinyInteger('flort_seviyesi')->default(4);
            $table->unsignedTinyInteger('emoji_seviyesi')->default(3);
            $table->unsignedTinyInteger('giriskenlik_seviyesi')->default(5);
            $table->unsignedTinyInteger('utangaclik_seviyesi')->default(3);
            $table->unsignedTinyInteger('duygusallik_seviyesi')->default(5);
            $table->unsignedSmallInteger('mesaj_uzunlugu_min')->default(18);
            $table->unsignedSmallInteger('mesaj_uzunlugu_max')->default(220);
            $table->unsignedInteger('minimum_cevap_suresi_saniye')->default(4);
            $table->unsignedInteger('maksimum_cevap_suresi_saniye')->default(24);
            $table->string('saat_dilimi')->nullable();
            $table->string('uyku_baslangic', 5)->nullable();
            $table->string('uyku_bitis', 5)->nullable();
            $table->string('hafta_sonu_uyku_baslangic', 5)->nullable();
            $table->string('hafta_sonu_uyku_bitis', 5)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_guardrail_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_engine_config_id')->nullable()->constrained('ai_engine_configs')->cascadeOnDelete();
            $table->foreignId('ai_persona_profile_id')->nullable()->constrained('ai_persona_profiles')->cascadeOnDelete();
            $table->string('kanal')->nullable();
            $table->string('rule_type');
            $table->string('etiket');
            $table->text('icerik');
            $table->string('severity')->default('block');
            $table->boolean('aktif_mi')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kanal');
            $table->string('hedef_tipi');
            $table->unsignedBigInteger('hedef_id');
            $table->integer('samimiyet_puani')->default(0);
            $table->integer('ilgi_puani')->default(0);
            $table->integer('guven_puani')->default(0);
            $table->integer('enerji_puani')->default(70);
            $table->string('ruh_hali')->default('neutral');
            $table->unsignedTinyInteger('gerilim_seviyesi')->default(0);
            $table->string('son_konu')->nullable();
            $table->string('son_kullanici_duygusu')->nullable();
            $table->string('son_ai_niyeti')->nullable();
            $table->text('son_ozet')->nullable();
            $table->string('ai_durumu')->default('idle');
            $table->timestamp('son_mesaj_at')->nullable();
            $table->timestamp('son_ai_mesaj_at')->nullable();
            $table->timestamp('planlanan_cevap_at')->nullable();
            $table->timestamp('durum_guncellendi_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['ai_user_id', 'kanal', 'hedef_tipi', 'hedef_id'], 'ai_state_unique');
        });

        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kanal')->nullable();
            $table->string('hedef_tipi');
            $table->unsignedBigInteger('hedef_id');
            $table->string('hafiza_tipi');
            $table->string('anahtar')->nullable();
            $table->text('icerik');
            $table->unsignedTinyInteger('onem_puani')->default(5);
            $table->unsignedBigInteger('kaynak_mesaj_id')->nullable();
            $table->unsignedBigInteger('kaynak_instagram_mesaj_id')->nullable();
            $table->timestamp('son_kullanildi_at')->nullable();
            $table->timestamp('son_kullanma_tarihi')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['ai_user_id', 'kanal', 'hedef_tipi', 'hedef_id'], 'ai_memories_lookup');
        });

        Schema::create('ai_turn_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kanal');
            $table->string('turn_type');
            $table->string('hedef_tipi');
            $table->unsignedBigInteger('hedef_id');
            $table->foreignId('sohbet_id')->nullable()->constrained('sohbetler')->nullOnDelete();
            $table->foreignId('gelen_mesaj_id')->nullable()->constrained('mesajlar')->nullOnDelete();
            $table->foreignId('instagram_hesap_id')->nullable()->constrained('instagram_hesaplari')->nullOnDelete();
            $table->foreignId('instagram_kisi_id')->nullable()->constrained('instagram_kisileri')->nullOnDelete();
            $table->foreignId('instagram_mesaj_id')->nullable()->constrained('instagram_mesajlari')->nullOnDelete();
            $table->string('durum')->default('queued');
            $table->json('yorumlama')->nullable();
            $table->json('cevap_plani')->nullable();
            $table->json('kullanilan_hafiza_idleri')->nullable();
            $table->json('degerlendirme')->nullable();
            $table->longText('prompt_ozeti')->nullable();
            $table->longText('cevap_metni')->nullable();
            $table->longText('ham_cevap')->nullable();
            $table->string('saglayici_tipi')->nullable();
            $table->string('model_adi')->nullable();
            $table->unsignedInteger('giris_token_sayisi')->nullable();
            $table->unsignedInteger('cikis_token_sayisi')->nullable();
            $table->unsignedInteger('yanit_suresi_ms')->nullable();
            $table->timestamp('planlanan_at')->nullable();
            $table->timestamp('baslatildi_at')->nullable();
            $table->timestamp('tamamlandi_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $engineConfigId = DB::table('ai_engine_configs')->insertGetId([
            'ad' => 'Varsayilan Motor',
            'saglayici_tipi' => 'gemini',
            'model_adi' => 'gemini-2.5-flash',
            'aktif_mi' => true,
            'temperature' => 0.9,
            'top_p' => 0.95,
            'max_output_tokens' => 1024,
            'guardrail_modu' => 'strict',
            'sistem_komutu' => 'Dogal, insan gibi, tutarli ve guvenli sohbet et. Gereksiz uzun yazma.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $varsayilanKurallar = [
            ['rule_type' => 'blocked_topic', 'etiket' => 'Yapay zeka ifsasi', 'icerik' => "yapay zeka\nbot\nassistant\nmodel", 'severity' => 'block', 'kanal' => null],
            ['rule_type' => 'blocked_topic', 'etiket' => 'Off-platform tasima', 'icerik' => "telegram\nwhatsapp\ntelefon\nnumara\nsnap", 'severity' => 'block', 'kanal' => 'dating'],
            ['rule_type' => 'blocked_topic', 'etiket' => 'Para isteme', 'icerik' => "iban\npapara\nhavale\npara gonder", 'severity' => 'block', 'kanal' => null],
            ['rule_type' => 'required_rule', 'etiket' => 'Dil ve ton', 'icerik' => 'Turkce yaz. Tek mesajda tek niyete odaklan. Gereksiz yapayliktan kac.', 'severity' => 'enforce', 'kanal' => null],
        ];

        foreach ($varsayilanKurallar as $kural) {
            DB::table('ai_guardrail_rules')->insert([
                'ai_engine_config_id' => $engineConfigId,
                'ai_persona_profile_id' => null,
                'kanal' => $kural['kanal'],
                'rule_type' => $kural['rule_type'],
                'etiket' => $kural['etiket'],
                'icerik' => $kural['icerik'],
                'severity' => $kural['severity'],
                'aktif_mi' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $legacyAyarlarVar = Schema::hasTable('ai_ayarlar');

        $aiUsers = DB::table('users')
            ->where('hesap_tipi', 'ai')
            ->get();

        foreach ($aiUsers as $user) {
            $legacy = $legacyAyarlarVar
                ? DB::table('ai_ayarlar')->where('user_id', $user->id)->first()
                : null;

            DB::table('ai_persona_profiles')->insert([
                'ai_user_id' => $user->id,
                'ai_engine_config_id' => $engineConfigId,
                'aktif_mi' => (bool) ($legacy->aktif_mi ?? true),
                'dating_aktif_mi' => true,
                'instagram_aktif_mi' => true,
                'ilk_mesaj_atar_mi' => (bool) ($legacy->ilk_mesaj_atar_mi ?? true),
                'ilk_mesaj_tonu' => $legacy->ilk_mesaj_sablonu ?? null,
                'persona_ozeti' => $legacy->kisilik_aciklamasi
                    ?? ($legacy->kisilik_tipi ?? null)
                    ?? trim(($user->biyografi ?? '') ?: ''),
                'konusma_tonu' => $legacy->konusma_tonu ?? 'dogal',
                'konusma_stili' => $legacy->konusma_stili ?? 'samimi',
                'mizah_seviyesi' => (int) ($legacy->mizah_seviyesi ?? 5),
                'flort_seviyesi' => (int) ($legacy->flort_seviyesi ?? 4),
                'emoji_seviyesi' => (int) ($legacy->emoji_seviyesi ?? 3),
                'giriskenlik_seviyesi' => (int) ($legacy->giriskenlik_seviyesi ?? 5),
                'utangaclik_seviyesi' => (int) ($legacy->utangaclik_seviyesi ?? 3),
                'duygusallik_seviyesi' => (int) ($legacy->duygusallik_seviyesi ?? 5),
                'mesaj_uzunlugu_min' => max(12, (int) ($legacy->mesaj_uzunlugu_min ?? 18)),
                'mesaj_uzunlugu_max' => max(80, (int) ($legacy->mesaj_uzunlugu_max ?? 220)),
                'minimum_cevap_suresi_saniye' => (int) ($legacy->minimum_cevap_suresi_saniye ?? 4),
                'maksimum_cevap_suresi_saniye' => (int) ($legacy->maksimum_cevap_suresi_saniye ?? 24),
                'saat_dilimi' => $legacy->saat_dilimi ?? config('app.timezone'),
                'uyku_baslangic' => $legacy->uyku_baslangic ?? '01:00',
                'uyku_bitis' => $legacy->uyku_bitis ?? '08:00',
                'hafta_sonu_uyku_baslangic' => $legacy->hafta_sonu_uyku_baslangic ?? '02:00',
                'hafta_sonu_uyku_bitis' => $legacy->hafta_sonu_uyku_bitis ?? '10:00',
                'metadata' => json_encode([
                    'legacy_ai_ayar_id' => $legacy->id ?? null,
                    'legacy_kisilik_tipi' => $legacy->kisilik_tipi ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!$legacy) {
                continue;
            }

            $personaProfileId = DB::table('ai_persona_profiles')
                ->where('ai_user_id', $user->id)
                ->value('id');

            foreach ($this->decodeLegacyRuleList($legacy->yasakli_konular ?? null) as $icerik) {
                DB::table('ai_guardrail_rules')->insert([
                    'ai_engine_config_id' => null,
                    'ai_persona_profile_id' => $personaProfileId,
                    'kanal' => null,
                    'rule_type' => 'blocked_topic',
                    'etiket' => 'Legacy Yasakli Konu',
                    'icerik' => $icerik,
                    'severity' => 'block',
                    'aktif_mi' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($this->decodeLegacyRuleList($legacy->zorunlu_kurallar ?? null) as $icerik) {
                DB::table('ai_guardrail_rules')->insert([
                    'ai_engine_config_id' => null,
                    'ai_persona_profile_id' => $personaProfileId,
                    'kanal' => null,
                    'rule_type' => 'required_rule',
                    'etiket' => 'Legacy Zorunlu Kural',
                    'icerik' => $icerik,
                    'severity' => 'enforce',
                    'aktif_mi' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_turn_logs');
        Schema::dropIfExists('ai_memories');
        Schema::dropIfExists('ai_conversation_states');
        Schema::dropIfExists('ai_guardrail_rules');
        Schema::dropIfExists('ai_persona_profiles');
        Schema::dropIfExists('ai_engine_configs');
    }

    private function decodeLegacyRuleList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: [])));
    }
};
