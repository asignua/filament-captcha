<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Enums;

enum Failure: string
{
    /**
     * No token was submitted (the visitor did not solve the challenge, or JavaScript is off).
     */
    case MissingToken = 'missing_token';

    /**
     * The provider answered `success: false` (bad, expired or already used token).
     */
    case Rejected = 'rejected';

    /**
     * reCAPTCHA v3: the score is below the threshold.
     */
    case LowScore = 'low_score';

    case WrongAction = 'wrong_action';
    case WrongHostname = 'wrong_hostname';

    /**
     * Timeout, connection error, non-2xx answer or a body that is not JSON.
     */
    case Unavailable = 'unavailable';

    /**
     * The driver has no keys and the configuration says to fail.
     */
    case NotConfigured = 'not_configured';

    public function messageKey(): string
    {
        return 'filament-captcha::filament-captcha.errors.'.$this->value;
    }
}
