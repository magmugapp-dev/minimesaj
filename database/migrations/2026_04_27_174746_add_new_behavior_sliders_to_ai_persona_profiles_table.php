<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_persona_profiles', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'flort_seviyesi', fn() => $table->unsignedTinyInteger('flort_seviyesi')->default(5)->after('konusma_imzasi'));
            $this->addColumnIfMissing($table, 'emoji_seviyesi', fn() => $table->unsignedTinyInteger('emoji_seviyesi')->default(3)->after('flort_seviyesi'));
            $this->addColumnIfMissing($table, 'giriskenlik_seviyesi', fn() => $table->unsignedTinyInteger('giriskenlik_seviyesi')->default(5)->after('emoji_seviyesi'));
            $this->addColumnIfMissing($table, 'utangaclik_seviyesi', fn() => $table->unsignedTinyInteger('utangaclik_seviyesi')->default(3)->after('giriskenlik_seviyesi'));
            $this->addColumnIfMissing($table, 'duygusallik_seviyesi', fn() => $table->unsignedTinyInteger('duygusallik_seviyesi')->default(5)->after('utangaclik_seviyesi'));
            $this->addColumnIfMissing($table, 'iyimserlik_seviyesi', fn() => $table->unsignedTinyInteger('iyimserlik_seviyesi')->default(7)->after('zeka_seviyesi'));
            $this->addColumnIfMissing($table, 'yaraticilik_seviyesi', fn() => $table->unsignedTinyInteger('yaraticilik_seviyesi')->default(6)->after('iyimserlik_seviyesi'));
            $this->addColumnIfMissing($table, 'detaycilik_seviyesi', fn() => $table->unsignedTinyInteger('detaycilik_seviyesi')->default(4)->after('yaraticilik_seviyesi'));
            $this->addColumnIfMissing($table, 'sosyallik_seviyesi', fn() => $table->unsignedTinyInteger('sosyallik_seviyesi')->default(7)->after('detaycilik_seviyesi'));
            $this->addColumnIfMissing($table, 'disiplin_seviyesi', fn() => $table->unsignedTinyInteger('disiplin_seviyesi')->default(5)->after('sosyallik_seviyesi'));
            $this->addColumnIfMissing($table, 'duzgunluk_seviyesi', fn() => $table->unsignedTinyInteger('duzgunluk_seviyesi')->default(6)->after('disiplin_seviyesi'));
            $this->addColumnIfMissing($table, 'liderlik_seviyesi', fn() => $table->unsignedTinyInteger('liderlik_seviyesi')->default(4)->after('duzgunluk_seviyesi'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_persona_profiles', function (Blueprint $table) {
            $columns = [
                'flort_seviyesi',
                'emoji_seviyesi',
                'giriskenlik_seviyesi',
                'utangaclik_seviyesi',
                'duygusallik_seviyesi',
                'iyimserlik_seviyesi',
                'yaraticilik_seviyesi',
                'detaycilik_seviyesi',
                'sosyallik_seviyesi',
                'disiplin_seviyesi',
                'duzgunluk_seviyesi',
                'liderlik_seviyesi',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('ai_persona_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addColumnIfMissing(Blueprint $table, string $column, callable $callback): void
    {
        if (!Schema::hasColumn($table->getTable(), $column)) {
            $callback();
        }
    }
};
