<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Component;

/**
 * <x-filament-captcha::scripts /> - the small client script, once, in the layout's head or before
 * Alpine/Livewire start. Filament panels get it from the plugin automatically.
 */
class Scripts extends Component
{
    private static ?string $version = null;

    public function render(): View
    {
        $path = config('filament-captcha.script_path');

        /** @var view-string $view */
        $view = 'filament-captcha::components.scripts';

        return view($view, [
            'src' => is_string($path) && $path !== '' ? url($path).'?v='.self::version() : null,
            'nonce' => Vite::cspNonce(),
        ]);
    }

    public static function scriptFile(): string
    {
        return dirname(__DIR__, 3).'/resources/js/captcha.js';
    }

    private static function version(): string
    {
        return self::$version ??= substr(md5_file(self::scriptFile()) ?: 'dev', 0, 10);
    }
}
