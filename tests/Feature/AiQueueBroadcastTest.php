<?php

use App\Events\InstagramAiCevapHazir;
use App\Events\YapayZekaCevabiHazir;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

it('broadcasts instagram ai replies immediately instead of queueing a second job', function () {
    expect(is_subclass_of(InstagramAiCevapHazir::class, ShouldBroadcastNow::class))->toBeTrue();
});

it('broadcasts dating ai replies immediately instead of queueing a second job', function () {
    expect(is_subclass_of(YapayZekaCevabiHazir::class, ShouldBroadcastNow::class))->toBeTrue();
});
