<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Rules;

use Asignua\FilamentCaptcha\CaptchaManager;
use Asignua\FilamentCaptcha\Enums\Failure;
use Asignua\FilamentCaptcha\VerificationRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a captcha token on the server.
 *
 *     'captcha' => [Captcha::rule()->action('contact')]
 *
 * Implicit: an empty or missing token fails (it does not skip the rule).
 */
class CaptchaRule implements ValidationRule
{
    public bool $implicit = true;

    private ?string $action = null;

    private ?float $minScore = null;

    /** @var bool|list<string>|null */
    private array|bool|null $hostnames = null;

    public function __construct(private ?string $driver = null) {}

    public function driver(string $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * reCAPTCHA v3 / Turnstile: the action the widget was rendered with.
     */
    public function action(?string $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * reCAPTCHA v3: minimum score, 0.0-1.0.
     */
    public function minScore(?float $score): static
    {
        $this->minScore = $score;

        return $this;
    }

    /**
     * @param bool|list<string>|null $hostnames allow-list; true = the current request host; null = config
     */
    public function hostnames(array|bool|null $hostnames): static
    {
        $this->hostnames = $hostnames;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = app(CaptchaManager::class)->verify(new VerificationRequest(
            token: is_string($value) ? $value : null,
            ip: request()->ip(),
            action: $this->action,
            minScore: $this->minScore,
            hostnames: $this->resolveHostnames(),
        ), $this->driver);

        if ($result->success) {
            return;
        }

        $fail(($result->failure ?? Failure::Rejected)->messageKey())->translate();
    }

    /**
     * @return list<string>|null
     */
    private function resolveHostnames(): ?array
    {
        $setting = $this->hostnames ?? config('filament-captcha.hostnames');

        if ($setting === true) {
            return [request()->getHost()];
        }

        if (!is_array($setting) || $setting === []) {
            return null;
        }

        return array_values(array_filter($setting, is_string(...)));
    }
}
