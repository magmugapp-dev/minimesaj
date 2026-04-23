<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hediyeler', function (Blueprint $table) {
            $table->id();
            $table->string('kod')->unique();
            $table->string('ad');
            $table->string('ikon')->nullable();
            $table->integer('puan_bedeli')->default(1);
            $table->boolean('aktif')->default(true);
            $table->integer('sira')->default(0);
            $table->timestamps();
        });

        Schema::table('hediye_gonderimleri', function (Blueprint $table) {
            if (!Schema::hasColumn('hediye_gonderimleri', 'hediye_id')) {
                $table->foreignId('hediye_id')
                    ->nullable()
                    ->after('alici_user_id')
                    ->constrained('hediyeler')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hediye_gonderimleri', function (Blueprint $table) {
            if (Schema::hasColumn('hediye_gonderimleri', 'hediye_id')) {
                $table->dropConstrainedForeignId('hediye_id');
            }
        });

        Schema::dropIfExists('hediyeler');
    }
};
