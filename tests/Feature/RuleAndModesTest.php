<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Feature;

use Asignua\FilamentCaptcha\Enums\Mode;
use Asignua\FilamentCaptcha\Facades\Captcha;
use Asignua\FilamentCaptcha\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RuleAndModesTest extends TestCase
{
    private const string TURNSTILE = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * @return list<string>
     */
    private function errors(mixed $value, ?\Closure $configure = null): array
    {
        $rule = Captcha::rule();

        if ($configure) {
            $configure($rule);
        }

        $validator = Validator::make(['captcha' => $value], ['captcha' => [$rule]]);

        return $validator->errors()->get('captcha');
    }

    public function test_a_valid_token_passes(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true])]);

        $this->assertSame([], $this->errors('good'));
    }

    public function test_a_missing_empty_or_non_string_token_fails_even_though_it_is_empty(): void
    {
        Http::fake();

        $message = __('filament-captcha::filament-captcha.errors.missing_token');

        $this->assertSame([$message], $this->errors(null));
        $this->assertSame([$message], $this->errors(''));
        $this->assertSame([$message], $this->errors(['x']));

        Http::assertNothingSent();
    }

    public function test_a_rejected_token_uses_the_translated_message(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

        $this->assertSame([__('filament-captcha::filament-captcha.errors.rejected')], $this->errors('used'));

        app()->setLocale('uk');
        $this->assertSame(['Перевірку безпеки не пройдено. Спробуйте ще раз.'], $this->errors('used'));
    }

    public function test_a_timeout_gives_the_unavailable_message_and_logs(): void
    {
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertSame([__('filament-captcha::filament-captcha.errors.unavailable')], $this->errors('t'));

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_fail_open_lets_the_visitor_through_when_the_provider_is_down(): void
    {
        config(['filament-captcha.fail_open' => true]);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertSame([], $this->errors('t'));
    }

    public function test_fail_open_does_not_cover_a_rejected_token(): void
    {
        config(['filament-captcha.fail_open' => true]);
        Http::fake([self::TURNSTILE => Http::response(['success' => false])]);

        $this->assertNotSame([], $this->errors('t'));
    }

    public function test_action_and_hostname_failures_have_their_own_messages(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'action' => 'login', 'hostname' => 'other.test'])]);

        $this->assertSame([__('filament-captcha::filament-captcha.errors.wrong_action')], $this->errors('a', fn ($rule) => $rule->action('register')));
        $this->assertSame([__('filament-captcha::filament-captcha.errors.wrong_hostname')], $this->errors('b', fn ($rule) => $rule->hostnames(['example.com'])));
    }

    public function test_hostnames_true_means_the_request_host_and_config_is_the_default(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'hostname' => 'localhost'])]);

        $this->assertSame([], $this->errors('a', fn ($rule) => $rule->hostnames(['localhost'])));
        $this->assertNotSame([], $this->errors('b', fn ($rule) => $rule->hostnames(['example.com'])));

        config(['filament-captcha.hostnames' => ['example.com']]);
        $this->assertNotSame([], $this->errors('c'));
        $this->assertSame([], $this->errors('d', fn ($rule) => $rule->hostnames(['localhost'])));
    }

    public function test_the_rule_can_target_another_driver(): void
    {
        Http::fake(['https://api.hcaptcha.com/siteverify' => Http::response(['success' => true])]);

        $validator = Validator::make(['c' => 'x'], ['c' => [Captcha::rule('hcaptcha')]]);

        $this->assertTrue($validator->passes());
    }

    public function test_the_rule_runs_the_provider_once_for_a_repeated_validation(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true])]);

        $this->assertSame([], $this->errors('once'));
        $this->assertSame([], $this->errors('once'));

        Http::assertSentCount(1);
    }

    public function test_fake_mode_passes_without_a_token_or_network(): void
    {
        Http::fake();
        Captcha::fake();

        $this->assertSame(Mode::Fake, Captcha::mode());
        $this->assertSame([], $this->errors(null));
        Http::assertNothingSent();
    }

    public function test_fake_can_be_told_to_fail_and_undone(): void
    {
        Captcha::fake(passes: false);
        $this->assertNotSame([], $this->errors('x'));

        Captcha::unfake();
        Http::fake([self::TURNSTILE => Http::response(['success' => true])]);
        $this->assertSame(Mode::Live, Captcha::mode());
        $this->assertSame([], $this->errors('x'));
    }

    public function test_the_fake_config_flag_works_like_the_facade(): void
    {
        config(['filament-captcha.fake' => true]);

        $this->assertSame(Mode::Fake, Captcha::mode());
        $this->assertSame([], $this->errors(null));
    }

    public function test_enabled_false_switches_the_captcha_off(): void
    {
        config(['filament-captcha.enabled' => false]);

        $this->assertSame(Mode::Disabled, Captcha::mode());
        $this->assertFalse(Captcha::isActive());
        $this->assertSame([], $this->errors(null));
    }

    public function test_missing_keys_in_a_local_environment_disable_the_captcha_with_one_loud_warning(): void
    {
        Log::spy();
        config(['filament-captcha.drivers.turnstile.site_key' => null, 'filament-captcha.drivers.turnstile.secret_key' => null]);

        $this->assertSame(Mode::Disabled, Captcha::mode());
        $this->assertSame([], $this->errors(null));
        $this->assertSame([], $this->errors(''));

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'DISABLED'));
    }

    public function test_missing_keys_fail_closed_when_the_policy_says_so(): void
    {
        config([
            'filament-captcha.missing_keys' => 'fail',
            'filament-captcha.drivers.turnstile.site_key' => '',
            'filament-captcha.drivers.turnstile.secret_key' => '',
        ]);

        $this->assertSame(Mode::Misconfigured, Captcha::mode());
        $this->assertSame([__('filament-captcha::filament-captcha.errors.not_configured')], $this->errors('anything'));
    }

    public function test_the_disable_policy_overrides_the_environment(): void
    {
        config(['filament-captcha.missing_keys' => 'disable', 'filament-captcha.drivers.turnstile.secret_key' => '']);

        $this->assertSame(Mode::Disabled, Captcha::mode());
    }

    public function test_production_without_keys_fails_closed_by_default(): void
    {
        $this->app['env'] = 'production';
        config(['filament-captcha.drivers.turnstile.site_key' => '']);

        $this->assertSame(Mode::Misconfigured, Captcha::mode());
        $this->assertNotSame([], $this->errors('anything'));

        $this->app['env'] = 'testing';
    }

    public function test_a_custom_driver_can_be_registered(): void
    {
        Captcha::extend('mine', fn (array $config, $http) => new class($config, $http) extends \Asignua\FilamentCaptcha\Drivers\TurnstileDriver
        {
            public function name(): string
            {
                return 'mine';
            }

            protected function endpoint(): string
            {
                return 'https://captcha.example/verify';
            }
        });
        config(['filament-captcha.drivers.mine' => ['site_key' => 'k', 'secret_key' => 's']]);
        Http::fake(['https://captcha.example/verify' => Http::response(['success' => true])]);

        $validator = Validator::make(['c' => 'x'], ['c' => [Captcha::rule('mine')]]);

        $this->assertTrue($validator->passes());
    }
}
