<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('sikayetler', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sikayet_eden_user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('hedef_tipi', ['user', 'mesaj']);
    $table->unsignedBigInteger('hedef_id');
    $table->string('kategori');
    $table->text('aciklama')->nullable();
    $table->enum('durum', ['bekliyor', 'inceleniyor', 'cozuldu', 'reddedildi'])->default('bekliyor');
    $table->text('yonetici_notu')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('sikayetler');
    }
};
