<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eslesme_gecilen_kullanicilar')) {
            Schema::create('eslesme_gecilen_kullanicilar', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('gecen_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('gecilen_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!$this->indexExists('eslesme_gec_unique')) {
            Schema::table('eslesme_gecilen_kullanicilar', function (Blueprint $table): void {
                $table->unique(
                    ['gecen_user_id', 'gecilen_user_id'],
                    'eslesme_gec_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eslesme_gecilen_kullanicilar');
    }

    private function indexExists(string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('eslesme_gecilen_kullanicilar')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return collect(DB::select("SHOW INDEX FROM eslesme_gecilen_kullanicilar WHERE Key_name = ?", [$indexName]))
            ->isNotEmpty();
    }
};
