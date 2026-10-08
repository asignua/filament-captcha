<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Drivers;

/**
 * Cloudflare Turnstile.
 */
class TurnstileDriver extends SiteVerifyDriver
{
    public function name(): string
    {
        return 'turnstile';
    }

    protected function endpoint(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    protected function checksAction(): bool
    {
        return true;
    }

    public function clientConfig(array $options = []): array
    {
        return parent::clientConfig($options) + [
            'kind' => 'turnstile',
            'mode' => 'widget',
            'scriptUrl' => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
        ];
    }

    public function cspSources(): array
    {
        return [
            'script-src' => ['https://challenges.cloudflare.com'],
            'frame-src' => ['https://challenges.cloudflare.com'],
            'connect-src' => ['https://challenges.cloudflare.com'],
        ];
    }
}
