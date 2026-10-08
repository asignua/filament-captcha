<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Pages;

use Asignua\FilamentCaptcha\Concerns\AddsCaptchaField;
use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    use AddsCaptchaField;

    protected function captchaScope(): string
    {
        return 'login';
    }
}
