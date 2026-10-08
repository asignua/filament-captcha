<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Drivers;

/**
 * Google reCAPTCHA v2, "I'm not a robot" checkbox.
 */
class RecaptchaV2Driver extends SiteVerifyDriver
{
    public function name(): string
    {
        return 'recaptcha_v2';
    }

    protected function endpoint(): string
    {
        return 'https://'.$this->domain().'/recaptcha/api/siteverify';
    }

    protected function domain(): string
    {
        $domain = $this->string('domain');

        return $domain !== '' ? $domain : 'www.google.com';
    }

    protected function invisible(): bool
    {
        return false;
    }

    public function clientConfig(array $options = []): array
    {
        // A checkbox key cannot be invisible (Google issues a separate key type: use recaptcha_v2_invisible),
        // and an invisible widget that is never executed would only produce missing tokens.
        if (!$this->invisible() && ($options['size'] ?? null) === 'invisible') {
            $options['size'] = null;
        }

        $config = parent::clientConfig($options);
        $locale = $config['locale'];

        return $config + [
            'kind' => 'recaptcha',
            'mode' => $this->invisible() ? 'execute' : 'widget',
            'scriptUrl' => 'https://'.$this->domain().'/recaptcha/api.js?render=explicit'.(is_string($locale) ? '&hl='.rawurlencode($locale) : ''),
        ];
    }

    public function cspSources(): array
    {
        $origin = 'https://'.$this->domain();

        return [
            'script-src' => [$origin, 'https://www.gstatic.com'],
            'frame-src' => [$origin, 'https://recaptcha.google.com'],
            'connect-src' => [$origin],
        ];
    }
}
