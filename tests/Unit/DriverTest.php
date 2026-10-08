<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Unit;

use Asignua\FilamentCaptcha\Enums\Failure;
use Asignua\FilamentCaptcha\Facades\Captcha;
use Asignua\FilamentCaptcha\Tests\TestCase;
use Asignua\FilamentCaptcha\VerificationRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class DriverTest extends TestCase
{
    private const array ENDPOINTS = [
        'recaptcha_v2' => 'https://www.google.com/recaptcha/api/siteverify',
        'recaptcha_v2_invisible' => 'https://www.google.com/recaptcha/api/siteverify',
        'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'hcaptcha' => 'https://api.hcaptcha.com/siteverify',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function drivers(): array
    {
        return array_map(static fn (string $name): array => [$name], array_combine(array_keys(self::ENDPOINTS), array_keys(self::ENDPOINTS)));
    }

    #[DataProvider('drivers')]
    public function test_a_valid_token_passes_and_the_secret_is_posted(string $driver): void
    {
        Http::fake([self::ENDPOINTS[$driver] => Http::response(['success' => true, 'score' => 0.9, 'action' => 'submit', 'hostname' => 'example.com'])]);

        $result = Captcha::verify(new VerificationRequest('token-1', '203.0.113.7'), $driver);

        $this->assertTrue($result->success);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::ENDPOINTS[$driver]
            && $request['secret'] === "secret-{$driver}"
            && $request['response'] === 'token-1'
            && $request['remoteip'] === '203.0.113.7');
    }

    #[DataProvider('drivers')]
    public function test_a_rejected_token_fails_with_the_provider_codes(string $driver): void
    {
        Http::fake([self::ENDPOINTS[$driver] => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $result = Captcha::verify(new VerificationRequest('bad'), $driver);

        $this->assertFalse($result->success);
        $this->assertSame(Failure::Rejected, $result->failure);
        $this->assertSame(['invalid-input-response'], $result->errorCodes);
    }

    #[DataProvider('drivers')]
    public function test_a_timeout_fails_closed_without_throwing(string $driver): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $result = Captcha::verify(new VerificationRequest('token'), $driver);

        $this->assertFalse($result->success);
        $this->assertSame(Failure::Unavailable, $result->failure);
    }

    #[DataProvider('drivers')]
    public function test_a_server_error_or_a_non_json_body_is_unavailable(string $driver): void
    {
        Http::fake([self::ENDPOINTS[$driver] => Http::response('<html>bad gateway</html>', 502)]);
        $this->assertSame(Failure::Unavailable, Captcha::verify(new VerificationRequest('token'), $driver)->failure);

        Http::fake([self::ENDPOINTS[$driver] => Http::response('not json', 200)]);
        $this->assertSame(Failure::Unavailable, Captcha::verify(new VerificationRequest('token-2'), $driver)->failure);
    }

    #[DataProvider('drivers')]
    public function test_an_empty_token_never_reaches_the_network(string $driver): void
    {
        Http::fake();

        foreach ([null, '', '   '] as $token) {
            $this->assertSame(Failure::MissingToken, Captcha::verify(new VerificationRequest($token), $driver)->failure);
        }

        Http::assertNothingSent();
    }

    #[DataProvider('drivers')]
    public function test_the_hostname_is_checked_when_an_allow_list_is_given(string $driver): void
    {
        Http::fake([self::ENDPOINTS[$driver] => Http::response(['success' => true, 'score' => 0.9, 'action' => 'submit', 'hostname' => 'Evil.test'])]);

        $this->assertSame(
            Failure::WrongHostname,
            Captcha::verify(new VerificationRequest('t1', hostnames: ['example.com']), $driver)->failure,
        );
        $this->assertTrue(Captcha::verify(new VerificationRequest('t2', hostnames: ['evil.TEST']), $driver)->success);
        // No list: no check.
        $this->assertTrue(Captcha::verify(new VerificationRequest('t3'), $driver)->success);
    }

    public function test_recaptcha_v3_rejects_a_low_score_and_honours_a_custom_threshold(): void
    {
        Http::fake([self::ENDPOINTS['recaptcha_v3'] => Http::response(['success' => true, 'score' => 0.4, 'action' => 'submit', 'hostname' => 'example.com'])]);

        $low = Captcha::verify(new VerificationRequest('a'), 'recaptcha_v3');
        $this->assertSame(Failure::LowScore, $low->failure);
        $this->assertSame(0.4, $low->score);

        $this->assertTrue(Captcha::verify(new VerificationRequest('b', minScore: 0.3), 'recaptcha_v3')->success);

        config(['filament-captcha.drivers.recaptcha_v3.score_threshold' => 0.9]);
        Http::fake([self::ENDPOINTS['recaptcha_v3'] => Http::response(['success' => true, 'score' => 0.8, 'action' => 'submit'])]);
        $this->assertSame(Failure::LowScore, Captcha::verify(new VerificationRequest('c'), 'recaptcha_v3')->failure);
    }

    public function test_recaptcha_v3_checks_the_action_and_defaults_to_the_configured_one(): void
    {
        Http::fake([self::ENDPOINTS['recaptcha_v3'] => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login'])]);

        $this->assertSame(Failure::WrongAction, Captcha::verify(new VerificationRequest('a'), 'recaptcha_v3')->failure); // default 'submit'
        $this->assertTrue(Captcha::verify(new VerificationRequest('b', action: 'login'), 'recaptcha_v3')->success);
        $this->assertSame(Failure::WrongAction, Captcha::verify(new VerificationRequest('c', action: 'contact'), 'recaptcha_v3')->failure);
    }

    public function test_turnstile_checks_the_action_only_when_one_is_expected(): void
    {
        Http::fake([self::ENDPOINTS['turnstile'] => Http::response(['success' => true, 'action' => 'login', 'hostname' => 'example.com'])]);

        $this->assertTrue(Captcha::verify(new VerificationRequest('a'), 'turnstile')->success);
        $this->assertTrue(Captcha::verify(new VerificationRequest('b', action: 'login'), 'turnstile')->success);
        $this->assertSame(Failure::WrongAction, Captcha::verify(new VerificationRequest('c', action: 'register'), 'turnstile')->failure);
    }

    public function test_recaptcha_v2_and_hcaptcha_ignore_action_and_score(): void
    {
        foreach (['recaptcha_v2', 'hcaptcha'] as $driver) {
            Http::fake([self::ENDPOINTS[$driver] => Http::response(['success' => true])]);

            $this->assertTrue(Captcha::verify(new VerificationRequest("t-{$driver}", action: 'anything', minScore: 0.99), $driver)->success);
        }
    }

    public function test_the_recaptcha_domain_is_configurable(): void
    {
        config(['filament-captcha.drivers.recaptcha_v2.domain' => 'www.recaptcha.net']);
        Http::fake(['https://www.recaptcha.net/recaptcha/api/siteverify' => Http::response(['success' => true])]);

        $this->assertTrue(Captcha::verify(new VerificationRequest('t'), 'recaptcha_v2')->success);
        $this->assertStringContainsString('https://www.recaptcha.net/recaptcha/api.js', Captcha::driver('recaptcha_v2')->clientConfig()['scriptUrl']);
    }

    public function test_the_same_token_is_verified_once_per_request(): void
    {
        Http::fake([self::ENDPOINTS['turnstile'] => Http::response(['success' => true])]);

        $this->assertTrue(Captcha::verify(new VerificationRequest('same'), 'turnstile')->success);
        $this->assertTrue(Captcha::verify(new VerificationRequest('same'), 'turnstile')->success);

        Http::assertSentCount(1);
    }

    public function test_the_client_config_per_driver(): void
    {
        $v3 = Captcha::clientConfig('recaptcha_v3', ['locale' => 'pt_BR']);
        $this->assertSame('execute', $v3['mode']);
        $this->assertSame('recaptcha', $v3['kind']);
        $this->assertSame('submit', $v3['action']);
        $this->assertSame('https://www.google.com/recaptcha/api.js?render=site-recaptcha_v3&hl=pt-BR', $v3['scriptUrl']);

        $this->assertSame('widget', Captcha::clientConfig('recaptcha_v2')['mode']);
        $this->assertSame('execute', Captcha::clientConfig('recaptcha_v2_invisible')['mode']);
        $this->assertSame('widget', Captcha::clientConfig('turnstile')['mode']);
        $this->assertSame('widget', Captcha::clientConfig('hcaptcha')['mode']);

        config(['filament-captcha.drivers.hcaptcha.size' => 'invisible']);
        $this->assertSame('execute', Captcha::clientConfig('hcaptcha')['mode']);
    }

    public function test_csp_sources_are_merged_by_directive(): void
    {
        $sources = Captcha::cspSources(['turnstile', 'hcaptcha']);

        $this->assertContains('https://challenges.cloudflare.com', $sources['script-src']);
        $this->assertContains('https://*.hcaptcha.com', $sources['frame-src']);
    }

    public function test_an_unknown_driver_is_an_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Captcha::driver('nope');
    }
}
