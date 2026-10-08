<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Concerns;

use Asignua\FilamentCaptcha\CaptchaPlugin;
use Asignua\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Schemas\Schema;
use Throwable;

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
        // The plugin is optional for pages that use this trait directly.
        $driver = filament()->hasPlugin('asignua-filament-captcha')
            ? CaptchaPlugin::get()->driverFor($this->captchaScope())
            : null;

        return Captcha::make('captcha')
            ->driver($driver)
            ->captchaAction($this->captchaScope())
            // Filament's Login runs authenticate() twice with multi-factor auth on; the token was spent on
            // the first (password) step. Only a payload Filament itself encrypted counts as "step two", so a
            // client cannot switch the captcha off by setting the property to garbage.
            ->hidden(fn (): bool => $this->captchaStepAlreadyPassed());
    }

    protected function captchaStepAlreadyPassed(): bool
    {
        if (!method_exists($this, 'getUserUndertakingMultiFactorAuthenticationData')) {
            return false;
        }

        try {
            return $this->getUserUndertakingMultiFactorAuthenticationData() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Also the reCAPTCHA v3 / Turnstile action name.
     */
    abstract protected function captchaScope(): string;
}
