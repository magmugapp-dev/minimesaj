<?php

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
uses(Tests\TestCase::class);

it('uses the current request host for relative public media paths', function () {
    Storage::fake('public');
    config()->set('app.url', 'http://127.0.0.1:8000');
    config()->set('app.public_url', 'http://192.168.1.104:8000');
    config()->set('filesystems.disks.public.url', 'http://127.0.0.1:8000/storage');
    Storage::disk('public')->put('fotograflar/32/ornek.jpg', 'image');

    app()->instance('request', Request::create('http://192.168.1.104:8000/api/auth/ben'));

    expect(MediaUrl::resolve('fotograflar/32/ornek.jpg'))
        ->toBe('http://192.168.1.104:8000/storage/fotograflar/32/ornek.jpg');
});

it('rewrites stored loopback urls to the configured public host', function () {
    Storage::fake('public');
    config()->set('app.url', 'http://127.0.0.1:8000');
    config()->set('app.public_url', 'http://192.168.1.104:8000');
    Storage::disk('public')->put('fotograflar/32/ornek.jpg', 'image');

    app()->instance('request', Request::create('http://127.0.0.1:8000/api/auth/ben'));

    expect(MediaUrl::resolve('http://127.0.0.1:8000/storage/fotograflar/32/ornek.jpg'))
        ->toBe('http://192.168.1.104:8000/storage/fotograflar/32/ornek.jpg');
});

it('extracts nested absolute urls before rewriting them', function () {
    Storage::fake('public');
    config()->set('app.url', 'http://127.0.0.1:8000');
    config()->set('app.public_url', 'http://192.168.1.104:8000');
    Storage::disk('public')->put('fotograflar/32/ornek.jpg', 'image');

    expect(MediaUrl::resolve('http://192.168.1.104:8000/http://127.0.0.1:8000/storage/fotograflar/32/ornek.jpg'))
        ->toBe('http://192.168.1.104:8000/storage/fotograflar/32/ornek.jpg');
});

it('returns null when a local public media file is missing', function () {
    Storage::fake('public');
    config()->set('app.url', 'http://127.0.0.1:8000');
    config()->set('app.public_url', 'http://192.168.1.104:8000');

    expect(MediaUrl::resolve('fotograflar/kullanicilar/26_1.jpg'))->toBeNull();
    expect(MediaUrl::resolve('http://192.168.1.104:8000/storage/fotograflar/kullanicilar/26_1.jpg'))->toBeNull();
});
