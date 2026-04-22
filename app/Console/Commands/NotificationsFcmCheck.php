<?php

namespace App\Console\Commands;

use App\Services\Notifications\FcmService;
use Illuminate\Console\Command;

class NotificationsFcmCheck extends Command
{
    protected $signature = 'notifications:fcm-check';

    protected $description = 'Check Firebase Cloud Messaging HTTP v1 configuration.';

    public function handle(FcmService $fcmService): int
    {
        $diagnostics = $fcmService->diagnostics();

        $this->table(
            ['Check', 'Value'],
            [
                ['configured', $diagnostics['configured'] ? 'yes' : 'no'],
                ['project_id', $diagnostics['project_id'] ?? '(missing)'],
                ['service_account_path', $diagnostics['service_account_path'] ?? '(missing)'],
                [
                    'GOOGLE_APPLICATION_CREDENTIALS',
                    $diagnostics['google_application_credentials'] ?? '(not set)',
                ],
                [
                    'checked_service_account_paths',
                    implode(', ', $diagnostics['checked_service_account_paths'] ?? []) ?: '(none)',
                ],
                [
                    'recommended_local_path',
                    $diagnostics['recommended_service_account_path'] ?? '(unknown)',
                ],
                [
                    'access_token_received',
                    $diagnostics['access_token_received'] ? 'yes' : 'no',
                ],
            ]
        );

        if (!empty($diagnostics['error'])) {
            $this->warn('Reason: ' . $diagnostics['error']);
        }

        if ($diagnostics['configured'] && $diagnostics['access_token_received']) {
            $this->info('FCM HTTP v1 is ready.');

            return self::SUCCESS;
        }

        $this->error('FCM HTTP v1 is not ready yet.');
        $this->line('Create/download a Firebase service account JSON key and point the app to it.');
        $this->line('Recommended setting value: ' . ($diagnostics['recommended_service_account_setting'] ?? 'ayarlar/firebase/service-account.json'));
        $this->line('Recommended local file path: ' . ($diagnostics['recommended_service_account_path'] ?? storage_path('app/private/ayarlar/firebase/service-account.json')));

        return self::FAILURE;
    }
}
