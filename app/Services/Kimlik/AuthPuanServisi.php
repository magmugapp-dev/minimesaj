<?php

namespace App\Services\Kimlik;

use App\Models\PuanHareketi;
use App\Models\User;
use App\Services\AyarServisi;
use App\Services\PuanServisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthPuanServisi
{
    public function __construct(
        private AyarServisi $ayarServisi,
        private PuanServisi $puanServisi,
    ) {}

    public function kayitBonusuUygula(User $user): void
    {
        $bonus = (int) $this->ayarServisi->al('kayit_puani', 0);
        if ($bonus <= 0) {
            return;
        }

        $zatenVerildi = PuanHareketi::query()
            ->where('user_id', $user->id)
            ->where('referans_tipi', 'kayit_bonusu')
            ->exists();

        if ($zatenVerildi) {
            return;
        }

        $this->puanServisi->ekle(
            $user,
            $bonus,
            'yonetici',
            'Kayit bonusu puani',
            'kayit_bonusu',
        );
    }

    public function gunlukGirisBonusuUygula(User $user): void
    {
        $bonus = (int) $this->ayarServisi->al('gunluk_giris_puani', 0);
        if ($bonus <= 0) {
            return;
        }

        $bugun = now()->toDateString();
        $takipKolonuVar = Schema::hasColumn('users', 'son_gunluk_giris_puani_tarihi');

        DB::transaction(function () use ($user, $bonus, $bugun, $takipKolonuVar): void {
            /** @var User|null $kilitliUser */
            $kilitliUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->first();

            if (!$kilitliUser) {
                return;
            }

            if ($takipKolonuVar) {
                if ($kilitliUser->son_gunluk_giris_puani_tarihi?->toDateString() === $bugun) {
                    return;
                }
            } else {
                $bugunVerildi = PuanHareketi::query()
                    ->where('user_id', $kilitliUser->id)
                    ->where('islem_tipi', 'yonetici')
                    ->where('referans_tipi', 'gunluk_giris_bonusu')
                    ->whereDate('created_at', $bugun)
                    ->exists();

                if ($bugunVerildi) {
                    return;
                }
            }

            $this->puanServisi->ekle(
                $kilitliUser,
                $bonus,
                'yonetici',
                'Gunluk giris bonusu puani',
                'gunluk_giris_bonusu',
            );

            if ($takipKolonuVar) {
                $kilitliUser->forceFill([
                    'son_gunluk_giris_puani_tarihi' => now(),
                ])->save();
            }

            $user->forceFill([
                'mevcut_puan' => $kilitliUser->mevcut_puan,
            ]);

            if ($takipKolonuVar) {
                $user->forceFill([
                    'son_gunluk_giris_puani_tarihi' => $kilitliUser->son_gunluk_giris_puani_tarihi,
                ]);
            }
        });
    }
}
