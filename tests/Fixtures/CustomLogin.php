<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Fixtures;

use Asignua\FilamentCaptcha\Concerns\AddsCaptchaField;
use Filament\Auth\Pages\Login;

/**
 * The README's "own page class" recipe, verbatim.
 */
class CustomLogin extends Login
{
    use AddsCaptchaField;

    protected function captchaScope(): string
    {
        return 'login';
    }
}
