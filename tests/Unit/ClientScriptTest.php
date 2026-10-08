<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Unit;

use Asignua\FilamentCaptcha\View\Components\Scripts;
use PHPUnit\Framework\TestCase;

/**
 * The browser script has no JS test runner here; these pin the contract with the PHP side.
 */
class ClientScriptTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(Scripts::scriptFile());
    }

    public function test_it_listens_for_the_event_the_trait_dispatches(): void
    {
        $this->assertStringContainsString("'filament-captcha:reset'", $this->script());
    }

    public function test_it_defines_the_factory_the_widget_view_calls_once(): void
    {
        $this->assertStringContainsString('window.filamentCaptcha = function', $this->script());
        $this->assertStringContainsString("typeof window.filamentCaptcha === 'function'", $this->script());
    }

    public function test_it_passes_the_csp_nonce_to_the_provider_script(): void
    {
        $this->assertStringContainsString("script.setAttribute('nonce', nonce)", $this->script());
    }

    public function test_every_client_config_key_it_reads_is_produced_by_the_drivers(): void
    {
        foreach (['kind', 'mode', 'siteKey', 'scriptUrl', 'driver', 'action', 'theme', 'size', 'locale', 'refresh', 'nonce', 'messages'] as $key) {
            $this->assertStringContainsString('config.'.$key, $this->script());
        }
    }
}
