<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Drivers;

/**
 * hCaptcha.
 */
class HCaptchaDriver extends SiteVerifyDriver
{
    public function name(): string
    {
        return 'hcaptcha';
    }

    protected function endpoint(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    public function clientConfig(array $options = []): array
    {
        $config = parent::clientConfig($options);
        $size = $config['size'] ?? $this->string('size');
        $locale = $config['locale'];

        return [
            'size' => $size !== '' ? $size : null,
            'mode' => $size === 'invisible' ? 'execute' : 'widget',
        ] + $config + [
            'kind' => 'hcaptcha',
            'scriptUrl' => 'https://js.hcaptcha.com/1/api.js?render=explicit&recaptchacompat=off'.(is_string($locale) ? '&hl='.rawurlencode($locale) : ''),
        ];
    }

    public function cspSources(): array
    {
        $origins = ['https://hcaptcha.com', 'https://*.hcaptcha.com'];

        return [
            'script-src' => $origins,
            'frame-src' => $origins,
            'style-src' => $origins,
            'connect-src' => $origins,
        ];
    }
}
