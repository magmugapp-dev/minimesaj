<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('app_translation_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('default_value')->nullable();
            $table->string('category')->nullable();
            $table->string('screen')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'screen']);
            $table->index('is_active');
        });

        Schema::create('app_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_translation_key_id')
                ->constrained('app_translation_keys')
                ->cascadeOnDelete();
            $table->foreignId('app_language_id')
                ->constrained('app_languages')
                ->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['app_translation_key_id', 'app_language_id'], 'app_translations_key_language_unique');
            $table->index('is_active');
        });

        Schema::create('app_legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->foreignId('app_language_id')
                ->constrained('app_languages')
                ->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'app_language_id'], 'app_legal_documents_type_language_unique');
            $table->index('is_active');
        });

        Schema::create('app_faq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_language_id')
                ->constrained('app_languages')
                ->cascadeOnDelete();
            $table->string('question');
            $table->text('answer')->nullable();
            $table->string('category')->nullable();
            $table->string('screen')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['app_language_id', 'sort_order']);
            $table->index(['category', 'screen']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_faq_items');
        Schema::dropIfExists('app_legal_documents');
        Schema::dropIfExists('app_translations');
        Schema::dropIfExists('app_translation_keys');
        Schema::dropIfExists('app_languages');
    }
};
