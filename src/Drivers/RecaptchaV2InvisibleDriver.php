<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Drivers;

/**
 * Google reCAPTCHA v2 invisible: the challenge opens on submit, only when Google is not sure. Needs its own
 * key pair (Google issues a separate key type for it).
 */
class RecaptchaV2InvisibleDriver extends RecaptchaV2Driver
{
    public function name(): string
    {
        return 'recaptcha_v2_invisible';
    }

    protected function invisible(): bool
    {
        return true;
    }
}
