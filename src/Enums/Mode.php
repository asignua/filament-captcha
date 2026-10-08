<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Enums;

enum Mode: string
{
    /**
     * Widget rendered, tokens verified against the provider.
     */
    case Live = 'live';

    /**
     * No widget, no network, validation passes (or fails on demand, see CaptchaManager::fake()).
     */
    case Fake = 'fake';

    /**
     * Switched off on purpose or because the keys are missing in a local environment.
     */
    case Disabled = 'disabled';

    /**
     * Keys are missing in an environment that must not run unprotected: validation fails.
     */
    case Misconfigured = 'misconfigured';
}
