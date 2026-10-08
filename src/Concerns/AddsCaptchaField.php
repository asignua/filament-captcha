<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Concerns;

use Asignua\FilamentCaptcha\CaptchaPlugin;
use Asignua\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Schemas\Schema;

/**
 * Appends the Captcha field to a Filament auth page's form. Use it in your own Login/Register/
 * RequestPasswordReset subclass when you do not want the plugin's ready-made pages:
 *
 *     class Login extends \Filament\Auth\Pages\Login { use AddsCaptchaField; }
 */
trait AddsCaptchaField
{
    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([
            ...$schema->getComponents(withHidden: true),
            $this->getCaptchaFormComponent(),
        ]);
    }

    protected function getCaptchaFormComponent(): Captcha
    {
        return Captcha::make('captcha')
            ->driver(CaptchaPlugin::get()->driverFor($this->captchaScope()))
            ->captchaAction($this->captchaScope());
    }

    /**
     * Also the reCAPTCHA v3 / Turnstile action name.
     */
    abstract protected function captchaScope(): string;
}
