<?php

use App\Models\Ayar;
use App\Services\AyarServisi;
use App\Services\Notifications\FcmService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

it('reads firebase http v1 settings from admin settings storage', function () {
    Cache::flush();
    Storage::fake('local');

    Storage::disk('local')->put(
        'ayarlar/firebase/test-service-account.json',
        json_encode([
            'type' => 'service_account',
            'project_id' => 'service-account-project',
            'private_key_id' => 'test-key',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk@test-project.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR)
    );

    Ayar::query()->updateOrCreate([
        'anahtar' => 'firebase_project_id',
    ], [
        'anahtar' => 'firebase_project_id',
        'deger' => 'magmug-2a095',
        'grup' => 'bildirimler',
        'tip' => 'string',
        'aciklama' => 'Firebase Project ID',
    ]);

    Ayar::query()->updateOrCreate([
        'anahtar' => 'firebase_service_account_path',
    ], [
        'anahtar' => 'firebase_service_account_path',
        'deger' => 'ayarlar/firebase/test-service-account.json',
        'grup' => 'bildirimler',
        'tip' => 'file',
        'aciklama' => 'Firebase Service Account JSON',
    ]);

    app(AyarServisi::class)->onbellekTemizle();

    $service = app(FcmService::class);
    $projectIdMethod = new ReflectionMethod($service, 'projectId');
    $projectIdMethod->setAccessible(true);
    $pathMethod = new ReflectionMethod($service, 'serviceAccountPath');
    $pathMethod->setAccessible(true);

    expect($service->configured())->toBeTrue();
    expect($projectIdMethod->invoke($service))->toBe('magmug-2a095');
    expect($pathMethod->invoke($service))->toContain('test-service-account.json');
});

it('falls back to firebase config when admin settings are blank', function () {
    Cache::flush();
    Storage::fake('local');

    Storage::disk('local')->put(
        'ayarlar/firebase/env-service-account.json',
        json_encode([
            'type' => 'service_account',
            'project_id' => 'service-account-project',
            'private_key_id' => 'test-key',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk@test-project.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR)
    );

    config()->set('services.firebase.project_id', 'magmug-2a095');
    config()->set('services.firebase.service_account_path', 'ayarlar/firebase/env-service-account.json');

    Ayar::query()->updateOrCreate([
        'anahtar' => 'firebase_project_id',
    ], [
        'anahtar' => 'firebase_project_id',
        'deger' => '',
        'grup' => 'bildirimler',
        'tip' => 'string',
        'aciklama' => 'Firebase Project ID',
    ]);

    Ayar::query()->updateOrCreate([
        'anahtar' => 'firebase_service_account_path',
    ], [
        'anahtar' => 'firebase_service_account_path',
        'deger' => '',
        'grup' => 'bildirimler',
        'tip' => 'file',
        'aciklama' => 'Firebase Service Account JSON',
    ]);

    app(AyarServisi::class)->onbellekTemizle();

    $service = app(FcmService::class);
    $projectIdMethod = new ReflectionMethod($service, 'projectId');
    $projectIdMethod->setAccessible(true);
    $pathMethod = new ReflectionMethod($service, 'serviceAccountPath');
    $pathMethod->setAccessible(true);

    expect($service->configured())->toBeTrue();
    expect($projectIdMethod->invoke($service))->toBe('magmug-2a095');
    expect($pathMethod->invoke($service))->toContain('env-service-account.json');
});
