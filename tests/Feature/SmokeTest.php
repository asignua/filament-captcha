<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Feature;

use Asignua\FilamentCaptcha\CaptchaPlugin;
use Asignua\FilamentCaptcha\Tests\TestCase;
use Filament\Facades\Filament;

class SmokeTest extends TestCase
{
    public function test_the_panel_boots(): void
    {
        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());
    }

    public function test_the_plugin_is_registered_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('asignua-filament-captcha'));
        $this->assertInstanceOf(CaptchaPlugin::class, $panel->getPlugin('asignua-filament-captcha'));
    }

    public function test_the_translations_are_loaded(): void
    {
        $this->assertSame('Please complete the security check.', __('filament-captcha::filament-captcha.errors.missing_token'));
    }
}
