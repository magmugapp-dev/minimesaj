<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesajlar', function (Blueprint $table): void {
            if (! Schema::hasColumn('mesajlar', 'client_message_id')) {
                $table->string('client_message_id', 96)->nullable()->after('cevaplanan_mesaj_id');
                $table->unique(
                    ['sohbet_id', 'gonderen_user_id', 'client_message_id'],
                    'mesajlar_client_message_unique'
                );
                $table->index(['sohbet_id', 'id'], 'mesajlar_sohbet_id_id_index');
            }
        });

        Schema::create('mobile_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_upload_id', 96);
            $table->string('mesaj_tipi', 24);
            $table->string('dosya_yolu');
            $table->string('mime_tipi')->nullable();
            $table->unsignedBigInteger('boyut')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_upload_id'], 'mobile_uploads_user_client_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_uploads');

        Schema::table('mesajlar', function (Blueprint $table): void {
            if (Schema::hasColumn('mesajlar', 'client_message_id')) {
                $table->dropUnique('mesajlar_client_message_unique');
                $table->dropIndex('mesajlar_sohbet_id_id_index');
                $table->dropColumn('client_message_id');
            }
        });
    }
};
