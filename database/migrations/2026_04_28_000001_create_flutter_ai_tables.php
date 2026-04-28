<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_characters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('character_id', 96)->unique();
            $table->unsignedInteger('character_version')->default(1);
            $table->string('schema_version', 24)->default('bv1.0');
            $table->boolean('active')->default(true);
            $table->string('display_name')->nullable();
            $table->string('username')->nullable();
            $table->string('primary_language_code', 12)->default('tr');
            $table->string('primary_language_name')->default('Turkish');
            $table->string('city')->nullable();
            $table->string('quality_tag', 8)->default('A');
            $table->json('character_json');
            $table->string('model_name')->default('gemini-2.5-flash');
            $table->decimal('temperature', 3, 2)->default(0.80);
            $table->decimal('top_p', 3, 2)->default(0.90);
            $table->unsignedInteger('max_output_tokens')->default(1024);
            $table->boolean('reengagement_active')->default(false);
            $table->unsignedInteger('reengagement_after_hours')->default(168);
            $table->unsignedInteger('reengagement_daily_limit')->default(1);
            $table->json('reengagement_templates')->nullable();
            $table->timestamp('last_reengagement_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'primary_language_code']);
        });

        Schema::create('ai_prompt_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 64)->unique();
            $table->string('hash', 64);
            $table->longText('prompt_xml');
            $table->boolean('active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('ai_message_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('sohbetler')->cascadeOnDelete();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('mesajlar')->nullOnDelete();
            $table->string('turn_type', 32)->default('reply');
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('planned_at')->nullable()->index();
            $table->timestamp('retry_after')->nullable()->index();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->string('idempotency_key', 128)->unique();
            $table->json('delivered_message_ids')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status', 'planned_at']);
            $table->index(['ai_user_id', 'status']);
        });

        Schema::create('ai_violation_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 64);
            $table->unsignedInteger('count')->default(0);
            $table->timestamp('last_violation_at')->nullable();
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->unique(['ai_user_id', 'user_id', 'category'], 'ai_violation_unique');
        });

        Schema::create('ai_block_thresholds', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 64)->unique();
            $table->unsignedInteger('threshold')->default(3);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::table('ai_block_thresholds')->insert([
            ['category' => 'absolute', 'threshold' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'underage', 'threshold' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'violence', 'threshold' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'harassment', 'threshold' => 3, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'sexual_pressure', 'threshold' => 3, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_block_thresholds');
        Schema::dropIfExists('ai_violation_counters');
        Schema::dropIfExists('ai_message_turns');
        Schema::dropIfExists('ai_prompt_versions');
        Schema::dropIfExists('ai_characters');
    }
};
