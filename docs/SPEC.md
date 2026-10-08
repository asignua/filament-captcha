# Spec: asignua/filament-captcha

Free (MIT). A captcha field for Filament 5 forms and a widget + trait for plain Livewire components.

## Scope (v1)

- Providers: Google reCAPTCHA v2 (checkbox), v2 invisible, v3 (score + action), Cloudflare Turnstile, hCaptcha
  (visible or invisible). Chosen globally (`CAPTCHA_DRIVER`) or per field / rule / widget.
- One field, `Forms\Components\Captcha::make()`, that attaches its own validation rule.
- One rule object, `Rules\CaptchaRule` (`Captcha::rule()`), usable anywhere in Laravel validation, array style.
- A Blade widget `<x-filament-captcha::widget wire:model="...">` + trait `InteractsWithCaptcha` for non-Filament Livewire.
- Opt-in captcha on Filament's Login / Register / RequestPasswordReset pages
  (`CaptchaPlugin::make()->login()->registration()->passwordReset()`), plus a trait for custom pages.
- Server verification: timeout, score threshold, action and hostname checks, translated failure messages.
- Modes: Live, Fake (`Captcha::fake()` / `CAPTCHA_FAKE`), Disabled (`CAPTCHA_ENABLED=false`, or missing keys in
  local/testing with a loud log warning), Misconfigured (missing keys elsewhere: validation FAILS).
- Livewire safety: `wire:ignore` + Alpine lifecycle (init/destroy survive morphs), token reset after every request that
  carried it, v3/invisible tokens fetched right before the form submits.
- CSP: the client script is a `<script src nonce>` (nonce from `Vite::cspNonce()`, the filament-csp-nonce convention);
  provider scripts are injected by it with the same nonce; `Captcha::cspSources()` lists what each provider needs.

## Public API

| Piece | Notes |
|---|---|
| `Forms\Components\Captcha` | `driver()`, `captchaAction()`, `minScore()`, `hostnames()`, `theme()`, `size()`, `locale()` |
| `Rules\CaptchaRule` | `driver()`, `action()`, `minScore()`, `hostnames()`; implicit (empty token fails) |
| `Facades\Captcha` | `rule()`, `verify()`, `fake()`/`unfake()`, `mode()`, `extend()`, `driver()`, `clientConfig()`, `cspSources()` |
| `Concerns\InteractsWithCaptcha` | `$captchaToken`, `validateCaptcha()`, `resetCaptcha()` |
| `Concerns\AddsCaptchaField` | for custom Filament auth pages |
| `CaptchaPlugin` | `login()`, `registration()`, `passwordReset()`, `loadScripts()` |
| Blade | `<x-filament-captcha::widget>`, `<x-filament-captcha::scripts />` |
| Config | `config/filament-captcha.php` |

## Extension points

- `Captcha::extend('name', fn (array $config, Factory $http) => new MyDriver(...))` with a `Contracts\CaptchaDriver`
  (or subclass `Drivers\SiteVerifyDriver` for a "POST secret + response" provider).
- Config `script_path = null` to serve `resources/js/captcha.js` yourself.
- Translations are overridable (`filament-captcha-translations`).

## Non-goals

- No image/text/math captcha, no honeypot/rate limiting (use Laravel's throttle for that).
- No per-user remembering of "already verified".
- No JS build step; the client script is hand-written ES5.
- Mixing reCAPTCHA v2 and v3 on one page is unsupported (both use the global `grecaptcha`).
