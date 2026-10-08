<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CaptchaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-captcha';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-captcha.php and are read as
        // `__('filament-captcha::filament-captcha.<key>')`. Publish tag: `filament-captcha-translations`.
        $package->name(static::$name)
            ->hasTranslations()
            ->hasViews();

        // Add a config file only when the plugin really has options: create config/filament-captcha.php and
        // chain `->hasConfigFile()` here (publish tag `filament-captcha-config`). Prefer fluent setters on the Plugin.
    }
}
