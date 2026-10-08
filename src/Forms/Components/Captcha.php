<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Forms\Components;

use Asignua\FilamentCaptcha\CaptchaManager;
use Asignua\FilamentCaptcha\Enums\Mode;
use Asignua\FilamentCaptcha\Rules\CaptchaRule;
use Closure;
use Filament\Forms\Components\Field;

/**
 * A captcha for Filament forms. Drop it into any schema; the matching validation rule is attached
 * automatically.
 *
 *     Captcha::make()                                  // default driver from config
 *     Captcha::make('captcha')->driver('recaptcha_v3')->captchaAction('login')->minScore(0.7)
 *
 * The state holds the provider's token; it is validated, never saved: read it from `$data` only if you
 * must, it is single-use.
 */
class Captcha extends Field
{
    protected string $view = 'filament-captcha::forms.components.captcha';

    protected string|Closure|null $driver = null;

    protected string|Closure|null $captchaAction = null;

    protected float|Closure|null $minScore = null;

    /** @var bool|Closure|list<string>|null */
    protected array|bool|Closure|null $hostnames = null;

    protected string|Closure|null $theme = null;

    protected string|Closure|null $size = null;

    protected string|Closure|null $locale = null;

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'captcha');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
        $this->default('');

        // Never write the (single-use) token to the model. The field is still validated: dehydration is
        // what skips saving, the rules run on the raw state.
        $this->dehydrated(false);

        $this->rule(fn (): CaptchaRule => app(CaptchaManager::class)
            ->rule($this->getDriver())
            ->action($this->getCaptchaAction())
            ->minScore($this->getMinScore())
            ->hostnames($this->getHostnames()));
    }

    public function driver(string|Closure|null $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * reCAPTCHA v3 / Turnstile action name. Named captchaAction() because Filament's own Component::action()
     * is taken.
     */
    public function captchaAction(string|Closure|null $action): static
    {
        $this->captchaAction = $action;

        return $this;
    }

    /**
     * reCAPTCHA v3 minimum score (0.0-1.0); config default otherwise.
     */
    public function minScore(float|Closure|null $score): static
    {
        $this->minScore = $score;

        return $this;
    }

    /**
     * @param bool|Closure|list<string>|null $hostnames
     */
    public function hostnames(array|bool|Closure|null $hostnames): static
    {
        $this->hostnames = $hostnames;

        return $this;
    }

    /**
     * auto | light | dark
     */
    public function theme(string|Closure|null $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * Provider-specific widget size (normal, compact, flexible, invisible ...).
     */
    public function size(string|Closure|null $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function locale(string|Closure|null $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDriver(): ?string
    {
        return $this->evaluate($this->driver);
    }

    public function getCaptchaAction(): ?string
    {
        return $this->evaluate($this->captchaAction);
    }

    public function getMinScore(): ?float
    {
        return $this->evaluate($this->minScore);
    }

    /**
     * @return bool|list<string>|null
     */
    public function getHostnames(): array|bool|null
    {
        return $this->evaluate($this->hostnames);
    }

    public function getTheme(): ?string
    {
        return $this->evaluate($this->theme);
    }

    public function getSize(): ?string
    {
        return $this->evaluate($this->size);
    }

    public function getLocale(): ?string
    {
        return $this->evaluate($this->locale);
    }

    /**
     * Draw anything at all? Fake and disabled modes render nothing (the rule passes there); a
     * misconfigured driver renders a visible error (the rule fails).
     */
    public function shouldRender(): bool
    {
        return in_array(app(CaptchaManager::class)->mode($this->getDriver()), [Mode::Live, Mode::Misconfigured], true);
    }
}
