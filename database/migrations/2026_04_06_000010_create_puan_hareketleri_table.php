<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('puan_hareketleri', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('islem_tipi', ['reklam', 'odeme', 'harcama', 'gunluk_hak', 'hediye', 'yonetici']);
    $table->integer('puan_miktari');
    $table->integer('onceki_bakiye')->default(0);
    $table->integer('sonraki_bakiye')->default(0);
    $table->string('aciklama')->nullable();
    $table->string('referans_tipi')->nullable();
    $table->unsignedBigInteger('referans_id')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('puan_hareketleri');
    }
};
