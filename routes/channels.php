<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Reverb üzerinden yayınlanan event'ler için kanal yetkilendirmeleri.
| Sunucu tarafında polling yok; tüm gerçek zamanlı iletişim websocket ile.
|
*/

// Dating: Kullanıcıya özel private kanal
Broadcast::channel('kullanici.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('kullanici.durum.{id}', function ($user, $id) {
    if ((int) $user->id === (int) $id) {
        return true;
    }

    return \App\Models\Eslesme::query()
        ->where('durum', 'aktif')
        ->where(function ($query) use ($user, $id) {
            $query->where(function ($inner) use ($user, $id) {
                $inner->where('user_id', $user->id)
                    ->where('eslesen_user_id', $id);
            })->orWhere(function ($inner) use ($user, $id) {
                $inner->where('user_id', $id)
                    ->where('eslesen_user_id', $user->id);
            });
        })
        ->exists();
});

// Dating: Sohbet odası kanalı
Broadcast::channel('sohbet.{sohbetId}', function ($user, $sohbetId) {
    $sohbet = \App\Models\Sohbet::with('eslesme')->find($sohbetId);
    if (!$sohbet) {
        return false;
    }
    $eslesme = $sohbet->eslesme;
    return $user->id === $eslesme->user_id || $user->id === $eslesme->eslesen_user_id;
});
