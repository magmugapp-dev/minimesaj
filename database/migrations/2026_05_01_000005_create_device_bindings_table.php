<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_bindings', function (Blueprint $table): void {
            $table->id();
            $table->string('device_fingerprint')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform', 20)->nullable();
            $table->boolean('banned')->default(false)->index();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('bound_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'banned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_bindings');
    }
};
