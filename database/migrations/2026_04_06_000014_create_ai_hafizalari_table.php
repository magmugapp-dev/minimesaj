<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('ai_hafizalari', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('hedef_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('sohbet_id')->nullable()->constrained('sohbetler')->nullOnDelete();
    $table->enum('hafiza_tipi', ['tercih', 'bilgi', 'duygu', 'ozet', 'sinir'])->default('bilgi');
    $table->text('icerik');
    $table->unsignedTinyInteger('onem_puani')->default(5);
    $table->timestamp('son_kullanma_tarihi')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_hafizalari');
    }
};
