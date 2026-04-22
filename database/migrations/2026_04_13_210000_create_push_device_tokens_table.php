<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_device_tokens')) {
            $this->repairExistingTable();

            return;
        }

        Schema::create('push_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->enum('platform', ['android', 'ios', 'web']);
            $table->string('cihaz_adi')->nullable();
            $table->string('uygulama_versiyonu')->nullable();
            $table->string('dil', 12)->nullable();
            $table->boolean('bildirim_izni')->default(true);
            $table->timestamp('son_gorulme_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_device_tokens');
    }

    private function repairExistingTable(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `push_device_tokens` MODIFY `token` VARCHAR(512) NOT NULL');

        $hasUniqueIndex = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'push_device_tokens')
            ->where('index_name', 'push_device_tokens_token_unique')
            ->exists();

        if (! $hasUniqueIndex) {
            DB::statement(
                'ALTER TABLE `push_device_tokens` ADD UNIQUE `push_device_tokens_token_unique` (`token`)'
            );
        }
    }
};
