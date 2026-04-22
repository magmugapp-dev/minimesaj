<?php

namespace App\Services\Kimlik\Sosyal;

final readonly class SosyalKimlikBilgisi
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        public ?string $displayName,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $avatarUrl = null,
    ) {}

    public function providerKolonu(): string
    {
        return match ($this->provider) {
            'apple' => 'apple_kimlik',
            'google' => 'google_kimlik',
        };
    }
}
