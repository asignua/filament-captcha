<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Feature;

use Asignua\FilamentCaptcha\CaptchaPlugin;
use Asignua\FilamentCaptcha\Enums\Failure;
use Asignua\FilamentCaptcha\Enums\Mode;
use Asignua\FilamentCaptcha\Facades\Captcha;
use Asignua\FilamentCaptcha\Pages\Login;
use Asignua\FilamentCaptcha\Pages\Register;
use Asignua\FilamentCaptcha\Tests\Fixtures\AlwaysOnMultiFactor;
use Asignua\FilamentCaptcha\Tests\Fixtures\CustomLogin;
use Asignua\FilamentCaptcha\Tests\TestCase;
use Asignua\FilamentCaptcha\VerificationRequest;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Workbench\App\Models\User;

/**
 * Independent review (08.10.2026). Tests marked "BUG" fail on purpose: they pin a defect reported in the
 * review findings. The rest cover edge cases the original suite did not.
 */
class IndependentReviewTest extends TestCase
{
    private const string TURNSTILE = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const string GOOGLE = 'https://www.google.com/recaptcha/api/siteverify';

    // ---------------------------------------------------------------- auth pages

    /**
     * BUG (filament-captcha-1): Filament's second login step (multi-factor challenge) calls
     * $this->form->getState() again, which re-runs the captcha rule on the hidden main form with a
     * token the provider has already consumed. A user with MFA can never finish logging in.
     */
    public function test_bug_a_user_with_multi_factor_authentication_can_finish_logging_in(): void
    {
        auth()->logout();
        Filament::getPanel('admin')->multiFactorAuthentication([new AlwaysOnMultiFactor]);
        Filament::setCurrentPanel('admin');

        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        // The provider accepts the token once, then reports it as used, as all of them do.
        Http::fake([self::TURNSTILE => Http::sequence()
            ->push(['success' => true, 'action' => 'login'])
            ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

        $page = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-pass', 'captcha' => 'token-1'])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertGuest();
        $this->assertNotNull($page->get('userUndertakingMultiFactorAuthentication'), 'The MFA step was not reached.');

        // Next HTTP request: the per-request token memo is gone.
        $this->app->forgetScopedInstances();

        $page->set('data.multiFactor.always.code', '123456')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    /**
     * BUG (filament-captcha-2): the README recipe for a custom auth page (AddsCaptchaField) crashes with
     * "Plugin [asignua-filament-captcha] is not registered" on a panel that does not register the plugin,
     * although the README says the plugin is only needed for the ready-made pages.
     */
    public function test_bug_the_trait_works_on_a_panel_without_the_plugin(): void
    {
        auth()->logout();
        // A panel object that never got ->plugin(CaptchaPlugin::make()).
        Filament::setCurrentPanel(Panel::make()->id('bare')->path('bare')->login(CustomLogin::class));

        $html = Livewire::test(CustomLogin::class)->html();

        $this->assertStringContainsString('filamentCaptcha(', $html);
    }

    public function test_registration_with_a_valid_captcha_creates_the_user_without_the_token(): void
    {
        auth()->logout();
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'action' => 'register'])]);

        Livewire::test(Register::class)
            ->fillForm(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'password-123', 'passwordConfirmation' => 'password-123', 'captcha' => 'good'])
            ->call('register')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'ann@example.com']);
    }

    public function test_the_register_page_sends_the_register_action_to_turnstile(): void
    {
        auth()->logout();
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'action' => 'login'])]);

        Livewire::test(Register::class)
            ->fillForm(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'password-123', 'passwordConfirmation' => 'password-123', 'captcha' => 'good'])
            ->call('register')
            ->assertHasErrors(['data.captcha' => __('filament-captcha::filament-captcha.errors.wrong_action')]);
    }

    public function test_the_plugin_keeps_a_driver_per_auth_page(): void
    {
        $plugin = CaptchaPlugin::make()->login(driver: 'recaptcha_v3')->registration();

        $this->assertSame('recaptcha_v3', $plugin->driverFor('login'));
        $this->assertNull($plugin->driverFor('register'));
        $this->assertNull($plugin->driverFor('unknown'));
    }

    // ---------------------------------------------------------------- verification edge cases

    /**
     * BUG (filament-captcha-3): with fail_open on, ANY non-2xx answer counts as "provider unavailable" and
     * lets the visitor through. A 4xx is the provider refusing THIS request (e.g. 413 for an oversized,
     * attacker-chosen token), not an outage.
     */
    public function test_bug_fail_open_does_not_let_a_client_error_through(): void
    {
        config(['filament-captcha.fail_open' => true]);
        Http::fake([self::TURNSTILE => Http::response('Request Entity Too Large', 413)]);

        $validator = Validator::make(['c' => str_repeat('A', 70_000)], ['c' => [Captcha::rule()]]);

        $this->assertTrue($validator->fails());
    }

    public function test_recaptcha_v3_without_a_score_fails_as_low_score(): void
    {
        Http::fake([self::GOOGLE => Http::response(['success' => true, 'action' => 'submit'])]);

        $this->assertSame(Failure::LowScore, Captcha::verify(new VerificationRequest('t'), 'recaptcha_v3')->failure);
    }

    public function test_recaptcha_v3_without_an_action_in_the_answer_fails_as_wrong_action(): void
    {
        Http::fake([self::GOOGLE => Http::response(['success' => true, 'score' => 0.9])]);

        $this->assertSame(Failure::WrongAction, Captcha::verify(new VerificationRequest('t'), 'recaptcha_v3')->failure);
    }

    public function test_a_truthy_but_not_boolean_success_is_rejected(): void
    {
        foreach (['true', 1, '1'] as $i => $success) {
            Http::fake([self::TURNSTILE => Http::response(['success' => $success])]);

            $this->assertSame(Failure::Rejected, Captcha::verify(new VerificationRequest("t{$i}"), 'turnstile')->failure);
        }
    }

    public function test_an_allow_list_with_no_hostname_in_the_answer_fails(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => true])]);

        $this->assertSame(Failure::WrongHostname, Captcha::verify(new VerificationRequest('t', hostnames: ['example.com']), 'turnstile')->failure);
    }

    public function test_hostnames_true_checks_the_current_request_host(): void
    {
        Http::fake([self::TURNSTILE => Http::sequence()
            ->push(['success' => true, 'hostname' => 'localhost'])
            ->push(['success' => true, 'hostname' => 'evil.test'])]);

        $this->assertTrue(Validator::make(['c' => 'a'], ['c' => [Captcha::rule()->hostnames(true)]])->passes());
        $this->assertSame(
            [__('filament-captcha::filament-captcha.errors.wrong_hostname')],
            Validator::make(['c' => 'b'], ['c' => [Captcha::rule()->hostnames(true)]])->errors()->get('c'),
        );
    }

    public function test_hostnames_false_on_the_rule_overrides_the_config_allow_list(): void
    {
        config(['filament-captcha.hostnames' => ['example.com']]);
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'hostname' => 'other.test'])]);

        $this->assertTrue(Validator::make(['c' => 'a'], ['c' => [Captcha::rule()->hostnames(false)]])->passes());
    }

    public function test_a_token_is_verified_again_in_the_next_request(): void
    {
        Http::fake([self::TURNSTILE => Http::sequence()
            ->push(['success' => true])
            ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

        $this->assertTrue(Captcha::verify(new VerificationRequest('replayed'))->success);

        $this->app->forgetScopedInstances();

        $this->assertSame(Failure::Rejected, Captcha::verify(new VerificationRequest('replayed'))->failure);
        Http::assertSentCount(2);
    }

    public function test_the_per_request_memo_is_keyed_by_driver(): void
    {
        Http::fake([
            self::TURNSTILE => Http::response(['success' => true]),
            'https://api.hcaptcha.com/siteverify' => Http::response(['success' => false]),
        ]);

        $this->assertTrue(Captcha::verify(new VerificationRequest('same'), 'turnstile')->success);
        $this->assertFalse(Captcha::verify(new VerificationRequest('same'), 'hcaptcha')->success);
    }

    public function test_disabled_and_fake_modes_never_touch_the_network(): void
    {
        Http::fake();

        config(['filament-captcha.enabled' => false]);
        $this->assertTrue(Captcha::verify(new VerificationRequest('t'))->success);

        config(['filament-captcha.enabled' => null]);
        Captcha::fake();
        $this->assertTrue(Captcha::verify(new VerificationRequest('t'))->success);

        Http::assertNothingSent();
    }

    public function test_fake_wins_over_a_misconfigured_driver(): void
    {
        config(['filament-captcha.missing_keys' => 'fail', 'filament-captcha.drivers.turnstile.secret_key' => '']);
        Captcha::fake();

        $this->assertSame(Mode::Fake, Captcha::mode());
    }

    public function test_the_missing_keys_warning_is_logged_once_per_driver(): void
    {
        Log::spy();
        config([
            'filament-captcha.drivers.turnstile.secret_key' => '',
            'filament-captcha.drivers.hcaptcha.secret_key' => '',
        ]);

        Captcha::mode('turnstile');
        Captcha::mode('turnstile');
        Captcha::mode('hcaptcha');

        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_the_rule_works_in_a_plain_controller(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => false])]);
        Route::post('/review-contact', static function (Request $request): string {
            $request->validate(['captcha_token' => [Captcha::rule()]]);

            return 'sent';
        });

        $this->postJson('/review-contact', ['captcha_token' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captcha_token' => __('filament-captcha::filament-captcha.errors.rejected')]);
    }

    // ---------------------------------------------------------------- rendering / security

    public function test_hostile_widget_props_cannot_break_out_of_the_x_data_attribute(): void
    {
        $evil = '"><script>alert(1)</script><b x="\'';

        $html = Blade::render('<x-filament-captcha::widget driver="turnstile" :action="$a" :locale="$a" />', ['a' => $evil]);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame(1, preg_match('/x-data="([^"]*)"/', $html, $m));
        $this->assertStringContainsString('filamentCaptcha({', $m[1]);
        $this->assertStringEndsWith('})', $m[1]);
        $this->assertSame($evil, $this->clientConfig($html)['action']);
    }

    public function test_the_secret_key_never_reaches_the_browser(): void
    {
        foreach (['recaptcha_v2', 'recaptcha_v2_invisible', 'recaptcha_v3', 'turnstile', 'hcaptcha'] as $driver) {
            $html = Blade::render('<x-filament-captcha::widget :driver="$d" />', ['d' => $driver]);

            $this->assertStringNotContainsString("secret-{$driver}", $html, $driver);
            $this->assertStringContainsString("site-{$driver}", $html, $driver);
        }
    }

    public function test_extra_classes_are_merged_onto_the_widget(): void
    {
        $html = Blade::render('<x-filament-captcha::widget class="my-4" />');

        $this->assertMatchesRegularExpression('/class="[^"]*fi-captcha[^"]*my-4|class="[^"]*my-4[^"]*fi-captcha/', $html);
    }

    /**
     * BUG (filament-captcha-6): Google documents frame-src https://recaptcha.google.com for reCAPTCHA (the
     * challenge frame can come from there); cspSources() omits it, so a strict policy built from it can
     * block the v2 image challenge.
     */
    public function test_bug_recaptcha_csp_sources_include_the_challenge_frame_origin(): void
    {
        $this->assertContains('https://recaptcha.google.com', Captcha::cspSources(['recaptcha_v2'])['frame-src']);
    }

    // ---------------------------------------------------------------- translations

    public function test_every_message_is_a_non_empty_string_and_ukrainian_is_really_translated(): void
    {
        $base = __DIR__.'/../../resources/lang';
        $english = Arr::dot(require $base.'/en/filament-captcha.php');
        $ukrainian = Arr::dot(require $base.'/uk/filament-captcha.php');

        foreach ((array) glob($base.'/*/filament-captcha.php') as $file) {
            foreach (Arr::dot(require (string) $file) as $key => $value) {
                $this->assertIsString($value, "{$file}: {$key}");
                $this->assertNotSame('', trim($value), "{$file}: {$key}");
            }
        }

        foreach ($english as $key => $value) {
            $this->assertNotSame($value, $ukrainian[$key], "uk.{$key} is still English");
        }
    }

    public function test_an_overlong_token_is_rejected_without_a_network_call(): void
    {
        Http::fake();

        $result = Captcha::verify(new VerificationRequest(str_repeat('A', 5000)), 'turnstile');

        $this->assertSame(Failure::Rejected, $result->failure);
        Http::assertNothingSent();
    }

    public function test_a_server_error_is_still_an_outage_under_fail_open(): void
    {
        config(['filament-captcha.fail_open' => true]);
        Http::fake([self::TURNSTILE => Http::response('boom', 502)]);

        $this->assertTrue(Captcha::verify(new VerificationRequest('t'), 'turnstile')->success);
    }

    public function test_the_fake_config_flag_is_ignored_in_production(): void
    {
        config(['filament-captcha.fake' => true]);
        $this->app['env'] = 'production';
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->assertNotSame(Mode::Fake, Captcha::mode());
    }
}
