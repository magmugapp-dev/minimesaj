<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('engellemeler', function (Blueprint $table) {
    $table->id();
    $table->foreignId('engelleyen_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('engellenen_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('sebep')->nullable();
    $table->timestamps();
    $table->unique(['engelleyen_user_id', 'engellenen_user_id']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('engellemeler');
    }
};
