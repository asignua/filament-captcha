# Changelog

All notable changes to `asignua/filament-captcha` are documented here.

## 1.0.0 - unreleased

- `Captcha::make()` field for Filament schemas with an attached validation rule; `<x-filament-captcha::widget>` and
  `InteractsWithCaptcha` for plain Livewire components.
- Providers: reCAPTCHA v2 (checkbox, invisible), reCAPTCHA v3, Cloudflare Turnstile, hCaptcha; custom drivers via `Captcha::extend()`.
- `CaptchaRule`: timeout, score threshold, action and hostname checks, translated messages (10 languages), fail-open option.
- Opt-in captcha on Filament Login / Register / RequestPasswordReset pages (`CaptchaPlugin`), `AddsCaptchaField` for custom pages.
- Fake mode (`Captcha::fake()`, `CAPTCHA_FAKE`), disabled mode, fail-closed mode for missing keys.
- Client script with Livewire morph safety, token reset after use, v3/invisible token fetched right before submit, CSP nonce support.
