<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('hediye_gonderimleri', function (Blueprint $table) {
    $table->id();
    $table->foreignId('gonderen_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('alici_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('hediye_adi');
    $table->integer('puan_bedeli')->default(0);
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('hediye_gonderimleri');
    }
};
