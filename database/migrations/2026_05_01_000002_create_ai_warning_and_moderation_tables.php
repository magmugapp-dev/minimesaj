<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gemini_api_warnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('error_code', 32);
            $table->text('error_message')->nullable();
            $table->foreignId('turn_id')->nullable()->constrained('ai_message_turns')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
        });

        Schema::create('ai_moderation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('sohbetler')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('dominance', 24)->nullable();
            $table->timestamp('lockout_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ai_user_id', 'user_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_moderation_events');
        Schema::dropIfExists('gemini_api_warnings');
    }
};
