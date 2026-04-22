<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destek_talepleri', function (Blueprint $table) {
            $table->text('yonetici_notu')->nullable()->after('durum');
        });

        Schema::create('destek_talebi_yanitlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destek_talebi_id')->constrained('destek_talepleri')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('mesaj');
            $table->timestamps();

            $table->index(['destek_talebi_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destek_talebi_yanitlari');

        Schema::table('destek_talepleri', function (Blueprint $table) {
            $table->dropColumn('yonetici_notu');
        });
    }
};
