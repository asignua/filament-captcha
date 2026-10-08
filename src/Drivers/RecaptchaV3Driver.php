<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Drivers;

/**
 * Google reCAPTCHA v3: no challenge, a 0.0-1.0 score per action.
 */
class RecaptchaV3Driver extends RecaptchaV2Driver
{
    public function name(): string
    {
        return 'recaptcha_v3';
    }

    public function defaultAction(): ?string
    {
        $action = $this->string('action');

        return $action !== '' ? $action : 'submit';
    }

    public function defaultMinScore(): ?float
    {
        $threshold = $this->config['score_threshold'] ?? 0.5;

        return is_numeric($threshold) ? (float) $threshold : 0.5;
    }

    protected function checksAction(): bool
    {
        return true;
    }

    public function clientConfig(array $options = []): array
    {
        $config = parent::clientConfig($options);
        $locale = $config['locale'];

        return [
            'mode' => 'execute',
            'scriptUrl' => 'https://'.$this->domain().'/recaptcha/api.js?render='.rawurlencode($this->siteKey()).(is_string($locale) ? '&hl='.rawurlencode($locale) : ''),
        ] + $config;
    }
}
