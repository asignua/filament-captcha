<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Feature;

use Asignua\FilamentCaptcha\Facades\Captcha as CaptchaFacade;
use Asignua\FilamentCaptcha\Forms\Components\Captcha;
use Asignua\FilamentCaptcha\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Workbench\App\Livewire\FilamentForm;

class FilamentFieldTest extends TestCase
{
    private const string TURNSTILE = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function tearDown(): void
    {
        FilamentForm::$field = null;

        parent::tearDown();
    }

    public function test_the_field_validates_the_token_on_submit(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true])]);

        Livewire::test(FilamentForm::class)
            ->set('data.name', 'Ann')
            ->set('data.captcha', 'good-token')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_the_saved_state_does_not_contain_the_single_use_token(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true])]);

        $component = Livewire::test(FilamentForm::class)
            ->set('data.name', 'Ann')
            ->set('data.captcha', 'good-token')
            ->call('save');

        $this->assertSame(['name' => 'Ann'], $component->get('saved'));
    }

    public function test_an_empty_token_blocks_the_submit(): void
    {
        Http::fake();

        Livewire::test(FilamentForm::class)
            ->set('data.name', 'Ann')
            ->call('save')
            ->assertHasErrors(['data.captcha'])
            ->assertSet('saved', null);

        Http::assertNothingSent();
    }

    public function test_a_rejected_token_blocks_the_submit_with_the_message(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => false])]);

        Livewire::test(FilamentForm::class)
            ->set('data.name', 'Ann')
            ->set('data.captcha', 'bad')
            ->call('save')
            ->assertHasErrors(['data.captcha' => __('filament-captcha::filament-captcha.errors.rejected')]);
    }

    public function test_the_widget_is_rendered_with_the_alpine_component_and_entangle(): void
    {
        $html = Livewire::test(FilamentForm::class)->html();

        $this->assertStringContainsString('filamentCaptcha(', $html);
        $this->assertStringContainsString('$wire.$entangle(', $html);
        $this->assertStringContainsString('data.captcha', $html);
        $this->assertStringContainsString('wire:ignore', $html);
        $this->assertSame('https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', $this->clientConfig($html)['scriptUrl']);
    }

    public function test_the_fluent_setters_reach_the_widget_and_the_rule(): void
    {
        FilamentForm::$field = Captcha::make('captcha')->driver('recaptcha_v3')->captchaAction('contact')->minScore(0.8)->hostnames(['example.com']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.7, 'action' => 'contact', 'hostname' => 'example.com'])]);

        $component = Livewire::test(FilamentForm::class);

        $this->assertStringContainsString('recaptcha_v3', $component->html());

        $component
            ->set('data.name', 'Ann')
            ->set('data.captcha', 'tok')
            ->call('save')
            ->assertHasErrors(['data.captcha' => __('filament-captcha::filament-captcha.errors.low_score')]);
    }

    public function test_fake_mode_renders_nothing_and_does_not_block(): void
    {
        CaptchaFacade::fake();

        $component = Livewire::test(FilamentForm::class);

        $this->assertStringNotContainsString('filamentCaptcha(', $component->html());

        $component->set('data.name', 'Ann')->call('save')->assertHasNoErrors();
    }

    public function test_a_misconfigured_driver_shows_an_error_and_blocks_the_submit(): void
    {
        config(['filament-captcha.missing_keys' => 'fail', 'filament-captcha.drivers.turnstile.secret_key' => '']);

        $component = Livewire::test(FilamentForm::class);

        $this->assertStringContainsString(__('filament-captcha::filament-captcha.errors.not_configured'), $component->html());

        $component->set('data.name', 'Ann')->set('data.captcha', 'x')->call('save')->assertHasErrors(['data.captcha']);
    }
}
