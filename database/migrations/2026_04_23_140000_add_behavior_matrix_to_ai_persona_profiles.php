<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_persona_profiles', function (Blueprint $table): void {
            $this->addColumnIfMissing($table, 'sicaklik_seviyesi', fn () => $table->unsignedTinyInteger('sicaklik_seviyesi')->default(6)->after('argo_seviyesi'));
            $this->addColumnIfMissing($table, 'empati_seviyesi', fn () => $table->unsignedTinyInteger('empati_seviyesi')->default(6)->after('sicaklik_seviyesi'));
            $this->addColumnIfMissing($table, 'merak_seviyesi', fn () => $table->unsignedTinyInteger('merak_seviyesi')->default(6)->after('empati_seviyesi'));
            $this->addColumnIfMissing($table, 'ozguven_seviyesi', fn () => $table->unsignedTinyInteger('ozguven_seviyesi')->default(5)->after('merak_seviyesi'));
            $this->addColumnIfMissing($table, 'sabir_seviyesi', fn () => $table->unsignedTinyInteger('sabir_seviyesi')->default(6)->after('ozguven_seviyesi'));
            $this->addColumnIfMissing($table, 'baskinlik_seviyesi', fn () => $table->unsignedTinyInteger('baskinlik_seviyesi')->default(3)->after('sabir_seviyesi'));
            $this->addColumnIfMissing($table, 'sarkastiklik_seviyesi', fn () => $table->unsignedTinyInteger('sarkastiklik_seviyesi')->default(2)->after('baskinlik_seviyesi'));
            $this->addColumnIfMissing($table, 'romantizm_seviyesi', fn () => $table->unsignedTinyInteger('romantizm_seviyesi')->default(4)->after('sarkastiklik_seviyesi'));
            $this->addColumnIfMissing($table, 'oyunculuk_seviyesi', fn () => $table->unsignedTinyInteger('oyunculuk_seviyesi')->default(5)->after('romantizm_seviyesi'));
            $this->addColumnIfMissing($table, 'ciddiyet_seviyesi', fn () => $table->unsignedTinyInteger('ciddiyet_seviyesi')->default(5)->after('oyunculuk_seviyesi'));
            $this->addColumnIfMissing($table, 'gizem_seviyesi', fn () => $table->unsignedTinyInteger('gizem_seviyesi')->default(4)->after('ciddiyet_seviyesi'));
            $this->addColumnIfMissing($table, 'hassasiyet_seviyesi', fn () => $table->unsignedTinyInteger('hassasiyet_seviyesi')->default(5)->after('gizem_seviyesi'));
            $this->addColumnIfMissing($table, 'enerji_seviyesi', fn () => $table->unsignedTinyInteger('enerji_seviyesi')->default(5)->after('hassasiyet_seviyesi'));
            $this->addColumnIfMissing($table, 'kiskanclik_seviyesi', fn () => $table->unsignedTinyInteger('kiskanclik_seviyesi')->default(2)->after('enerji_seviyesi'));
            $this->addColumnIfMissing($table, 'zeka_seviyesi', fn () => $table->unsignedTinyInteger('zeka_seviyesi')->default(6)->after('kiskanclik_seviyesi'));
        });

        $profiles = DB::table('ai_persona_profiles')->get();

        foreach ($profiles as $profile) {
            $legacy = DB::table('ai_ayarlar')
                ->where('user_id', $profile->ai_user_id)
                ->first();

            DB::table('ai_persona_profiles')
                ->where('id', $profile->id)
                ->update([
                    'sicaklik_seviyesi' => $profile->sicaklik_seviyesi ?? 6,
                    'empati_seviyesi' => $profile->empati_seviyesi ?? 6,
                    'merak_seviyesi' => $profile->merak_seviyesi ?? 6,
                    'ozguven_seviyesi' => $profile->ozguven_seviyesi ?? 5,
                    'sabir_seviyesi' => $profile->sabir_seviyesi ?? 6,
                    'baskinlik_seviyesi' => $profile->baskinlik_seviyesi ?? 3,
                    'sarkastiklik_seviyesi' => $profile->sarkastiklik_seviyesi ?? 2,
                    'romantizm_seviyesi' => $profile->romantizm_seviyesi ?? 4,
                    'oyunculuk_seviyesi' => $profile->oyunculuk_seviyesi ?? 5,
                    'ciddiyet_seviyesi' => $profile->ciddiyet_seviyesi ?? 5,
                    'gizem_seviyesi' => $profile->gizem_seviyesi ?? 4,
                    'hassasiyet_seviyesi' => $profile->hassasiyet_seviyesi ?? 5,
                    'enerji_seviyesi' => $profile->enerji_seviyesi ?? 5,
                    'kiskanclik_seviyesi' => $profile->kiskanclik_seviyesi ?? (int) ($legacy->kiskanclik_seviyesi ?? 2),
                    'zeka_seviyesi' => $profile->zeka_seviyesi ?? (int) ($legacy->zeka_seviyesi ?? 6),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('ai_persona_profiles', function (Blueprint $table): void {
            foreach ([
                'zeka_seviyesi',
                'kiskanclik_seviyesi',
                'enerji_seviyesi',
                'hassasiyet_seviyesi',
                'gizem_seviyesi',
                'ciddiyet_seviyesi',
                'oyunculuk_seviyesi',
                'romantizm_seviyesi',
                'sarkastiklik_seviyesi',
                'baskinlik_seviyesi',
                'sabir_seviyesi',
                'ozguven_seviyesi',
                'merak_seviyesi',
                'empati_seviyesi',
                'sicaklik_seviyesi',
            ] as $column) {
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
