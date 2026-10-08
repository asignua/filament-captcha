<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    | recaptcha_v2 | recaptcha_v2_invisible | recaptcha_v3 | turnstile | hcaptcha
    | A field, a rule or a Livewire widget can override it with ->driver('...').
    */
    'driver' => env('CAPTCHA_DRIVER', 'turnstile'),

    /*
    |--------------------------------------------------------------------------
    | Master switches
    |--------------------------------------------------------------------------
    | enabled: null = automatic (on, unless the keys are missing, see `missing_keys`);
    |          false = the captcha is off everywhere (widget hidden, rule passes).
    | fake:    true = no network and no widget, the rule passes. For test suites, or
    |          call Captcha::fake() from a test.
    */
    'enabled' => env('CAPTCHA_ENABLED'),
    'fake' => (bool) env('CAPTCHA_FAKE', false),

    /*
    |--------------------------------------------------------------------------
    | Missing keys
    |--------------------------------------------------------------------------
    | What happens when the chosen driver has no site key / secret:
    |   auto    - local and testing environments: the captcha is switched off with a
    |             loud log warning; every other environment: validation FAILS (a
    |             forgotten key must never open the form to bots);
    |   disable - always switch off (with the warning);
    |   fail    - always fail validation.
    */
    'missing_keys' => env('CAPTCHA_MISSING_KEYS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    | timeout:   seconds to wait for the provider's siteverify endpoint.
    | fail_open: let the visitor through when the provider is unreachable (timeout,
    |            5xx). Default false = fail closed.
    | hostnames: check the hostname the provider reports. null/false = do not check,
    |            true = the current request host, array = an allow-list.
    */
    'timeout' => (int) env('CAPTCHA_TIMEOUT', 5),
    'fail_open' => (bool) env('CAPTCHA_FAIL_OPEN', false),
    'hostnames' => null,

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    | theme: auto | light | dark (auto follows the `dark` class of <html>, as Filament does).
    | token_refresh: seconds between token refreshes for the execute-style drivers (v3,
    |                invisible); tokens live about two minutes.
    */
    'theme' => 'auto',
    'token_refresh' => 100,

    /*
    |--------------------------------------------------------------------------
    | Script route
    |--------------------------------------------------------------------------
    | The small client script is served by the package itself (no `filament:assets`,
    | works outside panels too). Set to null to disable the route and serve
    | resources/js/captcha.js from your own build.
    */
    'script_path' => 'filament-captcha/captcha.js',

    'drivers' => [
        'recaptcha_v2' => [
            'site_key' => env('CAPTCHA_RECAPTCHA_V2_SITE_KEY'),
            'secret_key' => env('CAPTCHA_RECAPTCHA_V2_SECRET_KEY'),
            // Use 'www.recaptcha.net' where google.com is blocked.
            'domain' => env('CAPTCHA_RECAPTCHA_DOMAIN', 'www.google.com'),
        ],
        'recaptcha_v2_invisible' => [
            'site_key' => env('CAPTCHA_RECAPTCHA_V2_INVISIBLE_SITE_KEY'),
            'secret_key' => env('CAPTCHA_RECAPTCHA_V2_INVISIBLE_SECRET_KEY'),
            'domain' => env('CAPTCHA_RECAPTCHA_DOMAIN', 'www.google.com'),
        ],
        'recaptcha_v3' => [
            'site_key' => env('CAPTCHA_RECAPTCHA_V3_SITE_KEY'),
            'secret_key' => env('CAPTCHA_RECAPTCHA_V3_SECRET_KEY'),
            'domain' => env('CAPTCHA_RECAPTCHA_DOMAIN', 'www.google.com'),
            'score_threshold' => (float) env('CAPTCHA_RECAPTCHA_V3_SCORE', 0.5),
            'action' => 'submit',
        ],
        'turnstile' => [
            'site_key' => env('CAPTCHA_TURNSTILE_SITE_KEY'),
            'secret_key' => env('CAPTCHA_TURNSTILE_SECRET_KEY'),
        ],
        'hcaptcha' => [
            'site_key' => env('CAPTCHA_HCAPTCHA_SITE_KEY'),
            'secret_key' => env('CAPTCHA_HCAPTCHA_SECRET_KEY'),
            // Set 'invisible' to run hCaptcha without a visible checkbox.
            'size' => env('CAPTCHA_HCAPTCHA_SIZE', 'normal'),
        ],
    ],

];
