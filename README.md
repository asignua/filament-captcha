# Filament Captcha

[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-captcha/tests.yml?branch=main&label=tests)](https://github.com/asignua/filament-captcha/actions/workflows/tests.yml)

TODO: one-paragraph pitch of what Captcha does and the problem it solves.

## Screenshots

TODO: add images to `art/` (cover.jpg first) and reference them here.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5

## Installation

```bash
composer require asignua/filament-captcha
```

Register the plugin on your panel:

```php
use Asignua\FilamentCaptcha\CaptchaPlugin;

$panel->plugin(CaptchaPlugin::make());
```

## Usage

TODO

## Configuration

TODO: fluent setters on `CaptchaPlugin`, or the published config (`php artisan vendor:publish --tag=filament-captcha-config`).

## Gotchas

TODO: the traps that cost time, each with the symptom and the fix.

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
