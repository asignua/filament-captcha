<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Pages;

use Asignua\FilamentCaptcha\Concerns\AddsCaptchaField;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    use AddsCaptchaField;

    protected function captchaScope(): string
    {
        return 'password_reset';
    }
}
