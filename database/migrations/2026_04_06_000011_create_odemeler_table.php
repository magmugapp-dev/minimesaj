<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('odemeler', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('platform', ['android', 'ios']);
    $table->enum('magaza_tipi', ['google_play', 'app_store']);
    $table->string('urun_kodu');
    $table->enum('urun_tipi', ['tek_seferlik', 'abonelik'])->default('tek_seferlik');
    $table->string('islem_kodu')->nullable();
    $table->decimal('tutar', 10, 2);
    $table->string('para_birimi', 10)->default('TRY');
    $table->string('durum')->default('bekliyor');
    $table->string('dogrulama_durumu')->default('bekliyor');
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('odemeler');
    }
};
