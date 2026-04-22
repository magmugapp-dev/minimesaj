<?php

namespace App\Services;

use App\Models\Engelleme;
use App\Models\PuanHareketi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PuanServisi
{
    public function harca(User $user, int $miktar, string $aciklama, ?string $referansTipi = null, ?int $referansId = null): PuanHareketi
    {
        if ($user->mevcut_puan < $miktar) {
            throw new \DomainException('Yetersiz puan.');
        }

        return DB::transaction(function () use ($user, $miktar, $aciklama, $referansTipi, $referansId) {
            $onceki = (int) ($user->mevcut_puan ?? 0);
            $user->decrement('mevcut_puan', $miktar);

            return PuanHareketi::create([
                'user_id' => $user->id,
                'islem_tipi' => 'harcama',
                'puan_miktari' => -$miktar,
                'onceki_bakiye' => $onceki,
                'sonraki_bakiye' => $onceki - $miktar,
                'aciklama' => $aciklama,
                'referans_tipi' => $referansTipi,
                'referans_id' => $referansId,
            ]);
        });
    }

    public function ekle(User $user, int $miktar, string $islemTipi, string $aciklama, ?string $referansTipi = null, ?int $referansId = null): PuanHareketi
    {
        return DB::transaction(function () use ($user, $miktar, $islemTipi, $aciklama, $referansTipi, $referansId) {
            $onceki = (int) ($user->mevcut_puan ?? 0);
            $user->increment('mevcut_puan', $miktar);

            return PuanHareketi::create([
                'user_id' => $user->id,
                'islem_tipi' => $islemTipi,
                'puan_miktari' => $miktar,
                'onceki_bakiye' => $onceki,
                'sonraki_bakiye' => $onceki + $miktar,
                'aciklama' => $aciklama,
                'referans_tipi' => $referansTipi,
                'referans_id' => $referansId,
            ]);
        });
    }
}
