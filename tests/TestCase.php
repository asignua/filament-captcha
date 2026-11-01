<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests;

use Asignua\FilamentCaptcha\CaptchaServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\AdminPanelProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->addNamespace('workbench', __DIR__.'/../workbench/resources/views');

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            CaptchaServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('logging.default', 'null');

        // Live mode with dummy keys; the network is always faked in tests.
        $app['config']->set('filament-captcha.driver', 'turnstile');

        foreach (['recaptcha_v2', 'recaptcha_v2_invisible', 'recaptcha_v3', 'turnstile', 'hcaptcha'] as $driver) {
            $app['config']->set("filament-captcha.drivers.{$driver}.site_key", "site-{$driver}");
            $app['config']->set("filament-captcha.drivers.{$driver}.secret_key", "secret-{$driver}");
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }

    /**
     * The widget config out of the rendered x-data="filamentCaptcha({ config: JSON.parse('...') })".
     *
     * @return array<string, mixed>
     */
    protected function clientConfig(string $html): array
    {
        // Filament 5.11 puts its own JSON.parse('…') payloads (field key, exposed methods) on the page too — take ours.
        $this->assertGreaterThan(0, preg_match_all("/JSON\\.parse\\('(.*?)'\\)/s", $html, $matches), 'No widget config in the HTML.');

        foreach ($matches[1] as $body) {
            // The payload is the body of a JS string literal; its escapes (\u0022, \/) are JSON-compatible.
            $json = json_decode('"'.$body.'"', false, flags: JSON_THROW_ON_ERROR);
            $config = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($config) && array_key_exists('scriptUrl', $config)) {
                return $config;
            }
        }

        $this->fail('No widget config in the HTML.');
    }
}
