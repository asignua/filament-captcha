<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Concerns;

use Asignua\FilamentCaptcha\CaptchaManager;
use Illuminate\Validation\ValidationException;

/**
 * For plain Livewire components that use <x-filament-captcha::widget wire:model="captchaToken" />.
 *
 *     public function submit(): void
 *     {
 *         $this->validateCaptcha(action: 'contact');
 *         ...
 *     }
 */
// Public API for host Livewire components: nothing in this package uses it, so phpstan sees it as unused.
// @phpstan-ignore trait.unused
trait InteractsWithCaptcha
{
    public string $captchaToken = '';

    /**
     * Verify $captchaToken, then reset the widget (tokens are single-use, passed or not).
     *
     * @param bool|list<string>|null $hostnames
     *
     * @throws ValidationException
     */
    protected function validateCaptcha(?string $driver = null, ?string $action = null, ?float $minScore = null, array|bool|null $hostnames = null): void
    {
        $rule = app(CaptchaManager::class)->rule($driver)->action($action)->minScore($minScore)->hostnames($hostnames);

        try {
            $this->validate(['captchaToken' => [$rule]]);
        } finally {
            $this->resetCaptcha();
        }
    }

    /**
     * Clear the token and tell the browser widget to start over.
     */
    public function resetCaptcha(): void
    {
        $this->captchaToken = '';
        $this->dispatch('filament-captcha:reset', id: $this->getId());
    }
}
