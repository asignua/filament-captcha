<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Contracts;

use Asignua\FilamentCaptcha\VerificationRequest;
use Asignua\FilamentCaptcha\VerificationResult;

/**
 * A captcha provider. Register your own with CaptchaManager::extend().
 */
interface CaptchaDriver
{
    public function name(): string;

    /**
     * Both the site key and the secret are present.
     */
    public function isConfigured(): bool;

    /**
     * Action expected when the caller names none (reCAPTCHA v3), null = do not check.
     */
    public function defaultAction(): ?string;

    /**
     * Score threshold used when the caller names none, null = no score check.
     */
    public function defaultMinScore(): ?float;

    /**
     * Server-side verification; must not throw.
     */
    public function verify(VerificationRequest $request): VerificationResult;

    /**
     * What the browser script needs to render the widget.
     *
     * @param array{action?: ?string, theme?: ?string, size?: ?string, locale?: ?string} $options
     *
     * @return array<string, mixed>
     */
    public function clientConfig(array $options = []): array;

    /**
     * Content-Security-Policy sources the provider needs, by directive.
     *
     * @return array<string, list<string>>
     */
    public function cspSources(): array;
}
