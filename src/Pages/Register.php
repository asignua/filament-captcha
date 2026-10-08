<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Pages;

use Asignua\FilamentCaptcha\Concerns\AddsCaptchaField;
use Filament\Auth\Pages\Register as BaseRegister;

class Register extends BaseRegister
{
    use AddsCaptchaField;

    protected function captchaScope(): string
    {
        return 'register';
    }
}
