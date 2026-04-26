<?php

namespace Database\Seeders;

use App\Models\AppFaqItem;
use App\Models\AppLanguage;
use App\Models\AppLegalDocument;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Models\Ayar;
use Illuminate\Database\Seeder;

class AppContentSeeder extends Seeder
{
    private const LANGUAGE_LABELS = [
        'tr' => ['Turkce', 'Turkce'],
        'en' => ['English', 'English'],
        'de' => ['German', 'Deutsch'],
        'fr' => ['French', 'Francais'],
    ];

    public function run(): void
    {
        $languages = $this->seedLanguages();
        $arbPayloads = $this->readArbPayloads();

        $this->seedTranslations($languages, $arbPayloads);
        $this->seedExtraTranslationKeys($languages);
        $this->seedRuntimeFallbackKeys($languages);
        $this->seedLegalDocuments($languages, $arbPayloads);
        $this->seedFaq($languages, $arbPayloads);
    }

    private function seedLanguages(): array
    {
        $codes = array_keys(self::LANGUAGE_LABELS);
        $languages = [];

        foreach ($codes as $index => $code) {
            [$name, $nativeName] = self::LANGUAGE_LABELS[$code];
            $languages[$code] = AppLanguage::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'native_name' => $nativeName,
                    'is_active' => true,
                    'is_default' => $code === 'tr',
                    'sort_order' => $index + 1,
                ]
            );
        }

        if (! AppLanguage::query()->where('is_default', true)->exists()) {
            AppLanguage::query()->where('code', 'tr')->update(['is_default' => true, 'is_active' => true]);
        }

        return $languages;
    }

    private function readArbPayloads(): array
    {
        $payloads = [];

        foreach (array_keys(self::LANGUAGE_LABELS) as $code) {
            $path = base_path("flutter/lib/l10n/app_{$code}.arb");
            if (! is_file($path)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                continue;
            }

            $payloads[$code] = collect($decoded)
                ->reject(fn($value, string $key): bool => str_starts_with($key, '@'))
                ->map(fn($value): string => (string) $value)
                ->all();
        }

        return $payloads;
    }

    private function seedTranslations(array $languages, array $arbPayloads): void
    {
        $defaultPayload = $arbPayloads['tr'] ?? reset($arbPayloads) ?: [];

        foreach ($defaultPayload as $key => $defaultValue) {
            $translationKey = AppTranslationKey::query()->firstOrCreate(
                ['key' => $key],
                [
                    'default_value' => $defaultValue,
                    'category' => $this->categoryFor($key),
                    'screen' => $this->screenFor($key),
                    'is_active' => true,
                ]
            );

            foreach ($arbPayloads as $languageCode => $payload) {
                if (! isset($languages[$languageCode])) {
                    continue;
                }

                AppTranslation::query()->firstOrCreate(
                    [
                        'app_translation_key_id' => $translationKey->id,
                        'app_language_id' => $languages[$languageCode]->id,
                    ],
                    [
                        'value' => $payload[$key] ?? $defaultValue,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedExtraTranslationKeys(array $languages): void
    {
        $items = [
            'profileContactChannelEmail' => [
                'category' => 'profile',
                'screen' => 'profile',
                'values' => [
                    'tr' => 'E-posta',
                    'en' => 'Email',
                    'de' => 'E-Mail',
                    'fr' => 'E-mail',
                ],
            ],
            'profileContactChannelSupport' => [
                'category' => 'profile',
                'screen' => 'profile',
                'values' => [
                    'tr' => 'Destek',
                    'en' => 'Support',
                    'de' => 'Support',
                    'fr' => 'Support',
                ],
            ],
            'profileAppVersion' => [
                'category' => 'profile',
                'screen' => 'profile',
                'values' => [
                    'tr' => '{appName} v{version}',
                    'en' => '{appName} v{version}',
                    'de' => '{appName} v{version}',
                    'fr' => '{appName} v{version}',
                ],
            ],
            'legalContentUnavailable' => [
                'category' => 'legal',
                'screen' => 'profile.legal',
                'values' => [
                    'tr' => 'Icerik su anda goruntulenemiyor.',
                    'en' => 'Content is not available right now.',
                    'de' => 'Der Inhalt ist derzeit nicht verfugbar.',
                    'fr' => 'Le contenu est indisponible pour le moment.',
                ],
            ],
            'apiErrorUsernameRequired' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Kullanici adi bos birakilamaz.', 'en' => 'Username cannot be empty.'],
            ],
            'apiErrorSocialSessionMissing' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Sosyal oturum bilgisi bulunamadi.', 'en' => 'Social session was not found.'],
            ],
            'apiErrorOnboardingIncomplete' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Onboarding alanlari tamamlanmadi.', 'en' => 'Onboarding fields are incomplete.'],
            ],
            'apiErrorSessionExpired' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Oturum suresi doldu.', 'en' => 'Your session has expired.'],
            ],
            'apiErrorMobileBootstrapUserUnreadable' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Mobil baslangic kullanici yaniti okunamadi.', 'en' => 'The mobile startup user response could not be read.'],
            ],
            'apiErrorUpdateFieldsMissing' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Guncellenecek alan bulunamadi.', 'en' => 'No fields were provided to update.'],
            ],
            'apiErrorMediaUpdateFieldsMissing' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Guncellenecek medya alani bulunamadi.', 'en' => 'No media fields were provided to update.'],
            ],
            'apiErrorMessageResponseUnreadable' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Mesaj gonderildi ama sunucu yaniti okunamadi.', 'en' => 'The message was sent, but the server response could not be read.'],
            ],
            'apiErrorTranslationTextUnreadable' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Ceviri alindi ama metin okunamadi.', 'en' => 'The translation was received, but the text could not be read.'],
            ],
            'apiErrorSessionAlreadyEnded' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Oturum zaten sonlanmis.', 'en' => 'The session has already ended.'],
            ],
            'apiErrorActiveSessionMissing' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Aktif oturum bulunamadi.', 'en' => 'No active session was found.'],
            ],
            'apiErrorConnection' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Sunucu ile baglanti kurulurken bir hata olustu.', 'en' => 'An error occurred while connecting to the server.'],
            ],
            'apiErrorServerUnreachable' => [
                'category' => 'error',
                'screen' => 'api',
                'values' => ['tr' => 'Sunucuya ulasilamadi.', 'en' => 'The server could not be reached.'],
            ],
            'commonUser' => [
                'category' => 'common',
                'screen' => 'common',
                'values' => ['tr' => 'Kullanici', 'en' => 'User'],
            ],
            'notificationFallbackTitle' => [
                'category' => 'notifications',
                'screen' => 'notifications',
                'values' => ['tr' => 'Bildirim', 'en' => 'Notification'],
            ],
            'chatPreviewPhotoSent' => [
                'category' => 'chat',
                'screen' => 'chat',
                'values' => ['tr' => 'Fotograf gonderildi', 'en' => 'Photo sent'],
            ],
            'chatPreviewVoiceSent' => [
                'category' => 'chat',
                'screen' => 'chat',
                'values' => ['tr' => 'Sesli mesaj gonderildi', 'en' => 'Voice message sent'],
            ],
            'chatPreviewVideoSent' => [
                'category' => 'chat',
                'screen' => 'chat',
                'values' => ['tr' => 'Video gonderildi', 'en' => 'Video sent'],
            ],
        ];

        foreach ($items as $key => $config) {
            $translationKey = AppTranslationKey::query()->firstOrCreate(
                ['key' => $key],
                [
                    'default_value' => $config['values']['tr'],
                    'category' => $config['category'],
                    'screen' => $config['screen'],
                    'is_active' => true,
                ]
            );

            foreach ($languages as $languageCode => $language) {
                AppTranslation::query()->firstOrCreate(
                    [
                        'app_translation_key_id' => $translationKey->id,
                        'app_language_id' => $language->id,
                    ],
                    [
                        'value' => $config['values'][$languageCode] ?? $config['values']['en'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedRuntimeFallbackKeys(array $languages): void
    {
        $libPath = base_path('flutter/lib');
        if (! is_dir($libPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($libPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'dart') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $matches = [];
            foreach ([
                "/(?:AppRuntimeText\\.instance\\.t|\\bruntime\\.t|\\b_text|\\b_t|\\b_purchaseText)\\(\\s*'([^']+)'\\s*,\\s*'((?:\\\\'|[^'])*)'/s",
                "/\\bkey:\\s*'([^']+)'\\s*,\\s*fallback:\\s*'((?:\\\\'|[^'])*)'/s",
                "/\\blabelKey:\\s*'([^']+)'\\s*,\\s*fallbackLabel:\\s*'((?:\\\\'|[^'])*)'/s",
                "/\\bdefaultNoteKey:\\s*'([^']+)'\\s*,\\s*defaultNote:\\s*'((?:\\\\'|[^'])*)'/s",
            ] as $pattern) {
                preg_match_all($pattern, $source, $patternMatches, PREG_SET_ORDER);
                $matches = array_merge($matches, $patternMatches);
            }

            foreach ($matches as $match) {
                $key = $match[1];
                $fallback = stripcslashes($match[2]);
                if (trim($key) === '' || trim($fallback) === '') {
                    continue;
                }

                $translationKey = AppTranslationKey::query()->firstOrCreate(
                    ['key' => $key],
                    [
                        'default_value' => $fallback,
                        'category' => $this->categoryFor($key),
                        'screen' => $this->screenFor($key),
                        'is_active' => true,
                    ]
                );

                foreach ($languages as $language) {
                    AppTranslation::query()->firstOrCreate(
                        [
                            'app_translation_key_id' => $translationKey->id,
                            'app_language_id' => $language->id,
                        ],
                        [
                            'value' => $fallback,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }

    private function seedLegalDocuments(array $languages, array $arbPayloads): void
    {
        $legacyValues = [
            AppLegalDocument::TYPE_PRIVACY => [
                'ayar' => 'gizlilik_politikasi',
                'titleKey' => 'privacyTitle',
                'fallbackTitle' => 'Gizlilik Politikasi',
                'bodyKeys' => ['privacyBody1', 'privacyBody2', 'privacyBody3', 'privacyBody4', 'privacyBody5'],
            ],
            AppLegalDocument::TYPE_KVKK => [
                'ayar' => 'kvkk_aydinlatma_metni',
                'titleKey' => 'profileKvkk',
                'fallbackTitle' => 'KVKK Aydinlatma Metni',
                'bodyKeys' => ['kvkkBody1', 'kvkkBody2', 'kvkkBody3'],
            ],
            AppLegalDocument::TYPE_TERMS => [
                'ayar' => 'kullanim_kosullari',
                'titleKey' => 'termsTitle',
                'fallbackTitle' => 'Kullanim Kosullari',
                'bodyKeys' => ['termsBody1', 'termsBody2', 'termsBody3', 'termsBody4', 'termsBody5'],
            ],
        ];

        foreach ($languages as $languageCode => $language) {
            $payload = $arbPayloads[$languageCode] ?? [];

            foreach ($legacyValues as $type => $config) {
                $legacy = $languageCode === 'tr'
                    ? (string) (Ayar::query()->where('anahtar', $config['ayar'])->value('deger') ?? '')
                    : '';
                $content = trim($legacy) !== ''
                    ? $legacy
                    : $this->combineArbBodies($payload, $config['bodyKeys']);

                AppLegalDocument::query()->firstOrCreate(
                    [
                        'type' => $type,
                        'app_language_id' => $language->id,
                    ],
                    [
                        'title' => $payload[$config['titleKey']] ?? $config['fallbackTitle'],
                        'content' => $content,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedFaq(array $languages, array $arbPayloads): void
    {
        foreach ($languages as $languageCode => $language) {
            $payload = $arbPayloads[$languageCode] ?? [];

            for ($index = 1; $index <= 3; $index++) {
                $question = trim((string) ($payload["helpFaqQuestion{$index}"] ?? ''));
                $answer = trim((string) ($payload["helpFaqAnswer{$index}"] ?? ''));

                if ($question === '' && $answer === '') {
                    continue;
                }

                AppFaqItem::query()->firstOrCreate(
                    [
                        'app_language_id' => $language->id,
                        'sort_order' => $index,
                    ],
                    [
                        'question' => $question,
                        'answer' => $answer,
                        'category' => 'help',
                        'screen' => 'profile.help',
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function combineArbBodies(array $payload, array $keys): string
    {
        return collect($keys)
            ->map(fn(string $key): string => trim((string) ($payload[$key] ?? '')))
            ->filter()
            ->implode("\n\n");
    }

    private function categoryFor(string $key): string
    {
        if (str_starts_with($key, 'profile') || str_starts_with($key, 'help') || str_starts_with($key, 'language')) {
            return 'profile';
        }
        if (str_starts_with($key, 'privacy') || str_starts_with($key, 'terms') || str_starts_with($key, 'kvkk')) {
            return 'legal';
        }
        if (str_starts_with($key, 'onboarding') || str_starts_with($key, 'social')) {
            return 'onboarding';
        }
        if (str_starts_with($key, 'match')) {
            return 'match';
        }
        if (str_starts_with($key, 'chat')) {
            return 'chat';
        }
        if (str_starts_with($key, 'notification')) {
            return 'notifications';
        }

        return 'common';
    }

    private function screenFor(string $key): string
    {
        $parts = preg_split('/(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY);
        $prefix = strtolower((string) ($parts[0] ?? 'common'));

        return match ($prefix) {
            'profile' => 'profile',
            'help' => 'profile.help',
            'privacy', 'terms', 'kvkk' => 'profile.legal',
            'onboarding', 'social' => 'onboarding',
            'match' => 'match',
            'chat' => 'chat',
            'notification' => 'notifications',
            default => 'common',
        };
    }
}
