<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Feature;

use Asignua\FilamentCaptcha\Facades\Captcha;
use Asignua\FilamentCaptcha\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Workbench\App\Livewire\ContactForm;

class LivewireComponentTest extends TestCase
{
    private const string TURNSTILE = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function test_the_widget_binds_to_the_wire_model(): void
    {
        $html = Livewire::test(ContactForm::class)->html();

        $this->assertStringContainsString('filamentCaptcha(', $html);
        $this->assertMatchesRegularExpression('/state: \\$wire\\.\\$entangle\\([^)]*captchaToken/', $html);
        $this->assertStringContainsString('wire:ignore', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="captcha_token"', $html);
        $this->assertSame('contact', $this->clientConfig($html)['action']);
    }

    public function test_a_valid_token_lets_the_form_through_and_the_token_is_cleared(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'action' => 'contact'])]);

        Livewire::test(ContactForm::class)
            ->set('message', 'Hello')
            ->set('captchaToken', 'good')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSet('captchaToken', '')
            ->assertDispatched('filament-captcha:reset');
    }

    public function test_a_failed_check_shows_the_error_and_resets_the_widget(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => false])]);

        Livewire::test(ContactForm::class)
            ->set('message', 'Hello')
            ->set('captchaToken', 'bad')
            ->call('submit')
            ->assertHasErrors(['captchaToken' => __('filament-captcha::filament-captcha.errors.rejected')])
            ->assertSet('sent', false)
            ->assertSet('captchaToken', '')
            ->assertDispatched('filament-captcha:reset');
    }

    public function test_a_missing_token_is_an_error(): void
    {
        Http::fake();

        Livewire::test(ContactForm::class)
            ->set('message', 'Hello')
            ->call('submit')
            ->assertHasErrors(['captchaToken'])
            ->assertSet('sent', false);
    }

    public function test_fake_mode_passes_a_component_without_a_token(): void
    {
        Captcha::fake();

        Livewire::test(ContactForm::class)
            ->set('message', 'Hello')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);
    }

    public function test_the_action_in_the_component_is_enforced(): void
    {
        config(['filament-captcha.driver' => 'recaptcha_v3']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'other'])]);

        Livewire::test(ContactForm::class)
            ->set('message', 'Hello')
            ->set('captchaToken', 'tok')
            ->call('submit')
            ->assertHasErrors(['captchaToken' => __('filament-captcha::filament-captcha.errors.wrong_action')]);
    }

    public function test_the_widget_outside_livewire_renders_a_hidden_input(): void
    {
        $html = Blade::render('<form><x-filament-captcha::widget name="cap" driver="hcaptcha" /></form>');

        $this->assertStringContainsString('name="cap"', $html);
        $this->assertSame('https://js.hcaptcha.com/1/api.js?render=explicit&recaptchacompat=off&hl=en', $this->clientConfig($html)['scriptUrl']);
        $this->assertStringNotContainsString('$entangle', $html);
    }

    public function test_the_widget_carries_the_csp_nonce_and_messages_to_the_script(): void
    {
        \Illuminate\Support\Facades\Vite::useCspNonce('abc123');

        $html = Blade::render('<x-filament-captcha::widget driver="turnstile" />');

        $config = $this->clientConfig($html);

        $this->assertSame('abc123', $config['nonce']);
        $this->assertSame(__('filament-captcha::filament-captcha.widget.load_failed'), $config['messages']['loadFailed']);
    }

    public function test_the_widget_renders_nothing_when_disabled(): void
    {
        config(['filament-captcha.enabled' => false]);

        $this->assertSame('', trim(Blade::render('<x-filament-captcha::widget />')));
    }

    public function test_the_scripts_tag_has_a_versioned_url_and_the_nonce(): void
    {
        \Illuminate\Support\Facades\Vite::useCspNonce('n0nce');

        $html = Blade::render('<x-filament-captcha::scripts />');

        $this->assertMatchesRegularExpression('#<script src="[^"]+/filament-captcha/captcha\.js\?v=[0-9a-f]{10}" \s+nonce="n0nce"\s*></script>#', $html);
    }

    public function test_the_scripts_tag_is_empty_when_the_route_is_off(): void
    {
        config(['filament-captcha.script_path' => null]);

        $this->assertSame('', trim(Blade::render('<x-filament-captcha::scripts />')));
    }

    public function test_the_script_is_served_with_long_cache_headers(): void
    {
        $response = $this->get('/filament-captcha/captcha.js');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('window.filamentCaptcha', (string) $response->getContent());
    }
}
