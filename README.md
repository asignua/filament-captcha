# Filament Captcha

[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-captcha/tests.yml?branch=main&label=tests)](https://github.com/asignua/filament-captcha/actions/workflows/tests.yml)

A captcha field for [Filament](https://filamentphp.com) 5 forms and plain Livewire components. One field, one validation
rule, five providers: **Google reCAPTCHA v2** (checkbox and invisible), **reCAPTCHA v3** (score and action),
**Cloudflare Turnstile** and **hCaptcha**. It re-initialises after Livewire morphs, resets the single-use token after every
failed submit, fetches v3 tokens right before the form goes out, and has a fake mode so your test suite never touches the
network.

## Screenshots

TODO: add images to `art/` (cover.jpg first) and reference them here: the field in a form, the login page with the widget,
the error state.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5 (which brings Livewire 4; the plain-Livewire widget uses the same)

## Installation

```bash
composer require asignua/filament-captcha
php artisan vendor:publish --tag=filament-captcha-config   # optional
```

Put the keys of the provider you use in `.env` (see [Configuration](#configuration)):

```dotenv
CAPTCHA_DRIVER=turnstile
CAPTCHA_TURNSTILE_SITE_KEY=...
CAPTCHA_TURNSTILE_SECRET_KEY=...
```

Register the plugin on the panel **only if** you want the captcha on Filament's auth pages, or want the client script
printed on every panel page automatically (the default):

```php
use Asignua\FilamentCaptcha\CaptchaPlugin;

$panel
    ->login()
    ->plugin(CaptchaPlugin::make()->login()->registration()->passwordReset());
```

Call `->plugin()` **after** `->login()` / `->registration()` / `->passwordReset()`: the plugin swaps in page classes that
add the field, and a later call on the panel would swap them back.

No `filament:assets` step: the small client script is served by the package itself (`/filament-captcha/captcha.js`,
versioned and cacheable for a year).

## Usage

### In a Filament form

```php
use Asignua\FilamentCaptcha\Forms\Components\Captcha;

$schema->components([
    TextInput::make('email')->email()->required(),
    Captcha::make(),                                   // default driver from config
]);

Captcha::make('captcha')
    ->driver('recaptcha_v3')
    ->captchaAction('contact')                         // v3 / Turnstile action name
    ->minScore(0.7)
    ->hostnames(['example.com'])
    ->theme('dark')                                    // auto (default) | light | dark
    ->locale('uk');
```

The field is validated, never saved: it is not dehydrated, so the single-use token does not end up in `$form->getState()`.
It is named `captchaAction()` and not `action()` because Filament's own `Component::action()` is taken.

### In a plain Livewire component

Once in your layout (Filament panels get it from the plugin):

```blade
<x-filament-captcha::scripts />
```

```php
use Asignua\FilamentCaptcha\Concerns\InteractsWithCaptcha;

class ContactForm extends Component
{
    use InteractsWithCaptcha;                          // public string $captchaToken

    public function submit(): void
    {
        $this->validateCaptcha(action: 'contact');     // throws ValidationException, resets the widget either way
        // ...
    }
}
```

```blade
<form wire:submit="submit">
    ...
    <x-filament-captcha::widget wire:model="captchaToken" action="contact" />
    @error('captchaToken') <p>{{ $message }}</p> @enderror
    <button type="submit">Send</button>
</form>
```

The widget also takes `driver`, `theme`, `size` and `locale`. Outside Livewire (no `wire:model`) it renders a hidden input
named `captcha_token` (`name="..."` to change it).

### As a validation rule

```php
use Asignua\FilamentCaptcha\Facades\Captcha;

$request->validate([
    'captcha' => [Captcha::rule()->action('contact')->minScore(0.7)],
]);
```

The rule is implicit: an empty or missing token fails with "Please complete the security check", it does not skip the rule.
Within one request the same token is verified once, so repeated validation does not burn it.

### Auth pages

`CaptchaPlugin::make()->login()->registration()->passwordReset()` adds the field to Filament's pages. Pass a driver per page
(`->login(driver: 'recaptcha_v3')`; the action is `login`, `register` or `password_reset`). With your own page classes use the
trait instead:

```php
class Login extends \Filament\Auth\Pages\Login
{
    use \Asignua\FilamentCaptcha\Concerns\AddsCaptchaField;

    protected function captchaScope(): string { return 'login'; }
}
```

The trait works without registering the plugin (the driver then comes from `config/filament-captcha.php`), but the page
still needs the client script: add `<x-filament-captcha::scripts />` through a panel render hook (for example
`PanelsRenderHook::HEAD_END`).

Multi-factor login: Filament runs `authenticate()` twice (password, then the challenge). The captcha is checked on the
first step only; on the challenge step the field is hidden, because the token was spent. This works for the plugin's pages
and for the trait alike.

### Tests

```php
use Asignua\FilamentCaptcha\Facades\Captcha;

Captcha::fake();                 // widget not drawn, nothing sent, validation passes
Captcha::fake(passes: false);    // validation fails
```

Or set `CAPTCHA_FAKE=true` in `.env.testing` (honoured only in `local` / `testing`; elsewhere it is ignored with a log warning). Without a call to `fake()`, use `Http::fake()` for the provider's siteverify
URL: the package resolves the HTTP client on every verification, so it honours your fake.

## Configuration

`config/filament-captcha.php`, all of it also reachable through `.env`:

| Key | Default | |
|---|---|---|
| `driver` (`CAPTCHA_DRIVER`) | `turnstile` | `recaptcha_v2`, `recaptcha_v2_invisible`, `recaptcha_v3`, `turnstile`, `hcaptcha` |
| `enabled` (`CAPTCHA_ENABLED`) | `null` | `false` turns the captcha off everywhere |
| `fake` (`CAPTCHA_FAKE`) | `false` | no widget, no network, validation passes |
| `missing_keys` (`CAPTCHA_MISSING_KEYS`) | `auto` | keys missing: `auto` = off with a log warning in local/testing, **fails** elsewhere; `disable`; `fail` |
| `timeout` (`CAPTCHA_TIMEOUT`) | `5` | seconds to wait for the provider |
| `fail_open` (`CAPTCHA_FAIL_OPEN`) | `false` | let visitors through when the provider is unreachable (a rejected token is never let through) |
| `hostnames` | `null` | `null` = no check, `true` = the request host, or an allow-list |
| `theme` | `auto` | `auto` follows the `dark` class on `<html>` |
| `token_refresh` | `100` | seconds between v3 token refreshes |
| `script_path` | `filament-captcha/captcha.js` | `null` to serve `resources/js/captcha.js` yourself |
| `drivers.*` | | `site_key`, `secret_key` per driver; reCAPTCHA `domain` (`www.recaptcha.net`), v3 `score_threshold` and default `action`, hCaptcha `size` (`invisible`) |

reCAPTCHA v2 checkbox and v2 invisible need **separate key pairs** (Google issues a different key type for each).

### Custom provider

```php
Captcha::extend('mine', fn (array $config, Factory $http) => new MyDriver($config, $http));
```

Implement `Asignua\FilamentCaptcha\Contracts\CaptchaDriver` (or extend `Drivers\SiteVerifyDriver`: set the endpoint and
`clientConfig()`; the client script knows the `recaptcha`, `turnstile` and `hcaptcha` kinds).

## Content-Security-Policy

The widget needs the provider's origins. `Captcha::cspSources(['turnstile'])` returns them by directive (`script-src`,
`frame-src`, `connect-src`, and `style-src` for hCaptcha). Add them to your policy. With
[asignua/filament-csp-nonce](https://github.com/asignua/filament-csp-nonce) the `directives()` you pass are merged over the
preset directive by directive, so include the sources the preset already has:

```php
CspNoncePlugin::make()->directives([
    'frame-src' => ["'self'", ...Captcha::cspSources()['frame-src']],
    'connect-src' => ["'self'", ...Captcha::cspSources()['connect-src']],
]);
```

The package prints `<script src nonce="...">` with `Vite::cspNonce()` and the client script gives the same nonce to the
provider's script tag it injects. With `'strict-dynamic'` that is enough; without it, allow the provider origins in `script-src`.

## Gotchas

- **A token is single-use.** The widget resets after every Livewire request that carried it, so a failed login makes the
  visitor tick the box again. A request that carries the token without verifying it (a `live()` field in the same form) also
  resets it. Only a request that called a method (a submit) resets the widget, so `live()` field updates do not undo a solved
  challenge. The reset event from `resetCaptcha()` is scoped to the Livewire component, so two forms on one page do not
  clear each other.
- **Never hide the field with `->visible()` / `->hidden()`** (the multi-factor step above is the one built-in exception). Hidden Filament fields are not validated, so that would open the
  form to bots. The field already draws nothing in fake / disabled mode (where the rule passes anyway) and draws an error in
  misconfigured mode (where the rule fails).
- **Missing keys in production fail the form**, on purpose. In `local` and `testing` they disable the captcha with a warning in
  the log; look for "DISABLED" there before wondering why a form accepts bots.
- **v3 `action` must match.** The widget asks Google for the action, the rule checks it comes back. Both default to the driver's
  `action` (`submit`); name it once on the field or pass the same string to `<x-filament-captcha::widget action>` and
  `validateCaptcha(action:)`.
- **reCAPTCHA v2 and v3 on one page do not mix** (one global `grecaptcha`).
- **Load the script before Alpine.** The plugin does it for panels; in your own layout put `<x-filament-captcha::scripts />` in
  the `<head>` or before `@livewireScripts`.
- **reCAPTCHA v3 badge.** If you hide it with CSS, Google requires the notice text in your form.

## Translations

The interface ships in English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish
under the `filament-captcha::filament-captcha` namespace. A test keeps every language in step with the English keys. Override a
string by publishing the translations (`--tag=filament-captcha-translations`) and editing the copy in
`lang/vendor/filament-captcha`.

## AI agents

The package ships [Laravel Boost](https://laravel.com/docs/boost) guidelines (`resources/boost/guidelines/core.blade.php`) so a
coding agent wires it up correctly.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
