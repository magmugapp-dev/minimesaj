<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('begeniler', function (Blueprint $table) {
    $table->id();
    $table->foreignId('begenen_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('begenilen_user_id')->constrained('users')->cascadeOnDelete();
    $table->boolean('eslesmeye_donustu_mu')->default(false);
    $table->boolean('goruldu_mu')->default(false);
    $table->timestamps();
    $table->unique(['begenen_user_id', 'begenilen_user_id']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('begeniler');
    }
};
