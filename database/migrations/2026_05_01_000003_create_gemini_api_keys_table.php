<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gemini_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->nullable();
            $table->text('api_key');
            $table->boolean('active')->default(true)->index();
            $table->integer('priority')->default(0)->index();
            $table->timestamp('exhausted_until')->nullable()->index();
            $table->unsignedBigInteger('total_requests')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gemini_api_keys');
    }
};
