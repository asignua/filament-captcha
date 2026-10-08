<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha;

use Asignua\FilamentCaptcha\Http\Controllers\ScriptController;
use Asignua\FilamentCaptcha\Support\VerifiedTokens;
use Asignua\FilamentCaptcha\View\Components\Scripts;
use Asignua\FilamentCaptcha\View\Components\Widget;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
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
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CaptchaManager::class);
        $this->app->scoped(VerifiedTokens::class);
    }

    public function packageBooted(): void
    {
        Blade::component('filament-captcha::widget', Widget::class);
        Blade::component('filament-captcha::scripts', Scripts::class);

        $path = config('filament-captcha.script_path');

        if (is_string($path) && $path !== '') {
            Route::get($path, ScriptController::class)->name('filament-captcha.script');
        }
    }
}
