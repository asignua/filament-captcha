<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha;

use Asignua\FilamentCaptcha\Contracts\CaptchaDriver;
use Asignua\FilamentCaptcha\Drivers\HCaptchaDriver;
use Asignua\FilamentCaptcha\Drivers\RecaptchaV2Driver;
use Asignua\FilamentCaptcha\Drivers\RecaptchaV2InvisibleDriver;
use Asignua\FilamentCaptcha\Drivers\RecaptchaV3Driver;
use Asignua\FilamentCaptcha\Drivers\TurnstileDriver;
use Asignua\FilamentCaptcha\Enums\Failure;
use Asignua\FilamentCaptcha\Enums\Mode;
use Asignua\FilamentCaptcha\Rules\CaptchaRule;
use Asignua\FilamentCaptcha\Support\VerifiedTokens;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class CaptchaManager
{
    /** @var array<string, Closure(array<string, mixed>, Factory): CaptchaDriver> */
    private array $custom = [];

    /** @var array<string, true> */
    private array $warned = [];

    private ?bool $fake = null;

    private bool $fakePasses = true;

    public function __construct(
        private readonly Application $app,
        private readonly LoggerInterface $log,
    ) {}

    public function defaultDriver(): string
    {
        $driver = config('filament-captcha.driver');

        return is_string($driver) && $driver !== '' ? $driver : 'turnstile';
    }

    /**
     * Register a driver (or replace a built-in one).
     *
     * @param Closure(array<string, mixed>, Factory): CaptchaDriver $factory receives the driver's config block
     */
    public function extend(string $name, Closure $factory): static
    {
        $this->custom[$name] = $factory;

        return $this;
    }

    public function driver(?string $name = null): CaptchaDriver
    {
        $name ??= $this->defaultDriver();

        $config = config('filament-captcha.drivers.'.$name, []);
        $config = is_array($config) ? $config : [];
        $timeout = config('filament-captcha.timeout', 5);
        $timeout = is_numeric($timeout) ? (int) $timeout : 5;

        // Resolved on every call, not cached: Http::fake() swaps the factory instance in the container.
        $http = $this->app->make(Factory::class);

        return match (true) {
            isset($this->custom[$name]) => ($this->custom[$name])($config, $http),
            $name === 'recaptcha_v2' => new RecaptchaV2Driver($config, $http, $timeout),
            $name === 'recaptcha_v2_invisible' => new RecaptchaV2InvisibleDriver($config, $http, $timeout),
            $name === 'recaptcha_v3' => new RecaptchaV3Driver($config, $http, $timeout),
            $name === 'turnstile' => new TurnstileDriver($config, $http, $timeout),
            $name === 'hcaptcha' => new HCaptchaDriver($config, $http, $timeout),
            default => throw new InvalidArgumentException("Unknown captcha driver [{$name}]."),
        };
    }

    /**
     * Skip the network and the widget: every verification passes (or fails, with $passes = false).
     */
    public function fake(bool $passes = true): static
    {
        $this->fake = true;
        $this->fakePasses = $passes;

        return $this;
    }

    public function unfake(): static
    {
        $this->fake = null;
        $this->fakePasses = true;

        return $this;
    }

    public function mode(?string $driver = null): Mode
    {
        if ($this->fake === true) {
            return Mode::Fake;
        }

        if ((bool) config('filament-captcha.fake', false)) {
            // The config flag is for local/testing only: a forgotten CAPTCHA_FAKE must not open production.
            if ($this->app->isLocal() || $this->app->runningUnitTests()) {
                return Mode::Fake;
            }

            $this->warnFakeIgnored();
        }

        if (config('filament-captcha.enabled') === false || config('filament-captcha.enabled') === 'false') {
            return Mode::Disabled;
        }

        $driver ??= $this->defaultDriver();

        if ($this->driver($driver)->isConfigured()) {
            return Mode::Live;
        }

        $policy = config('filament-captcha.missing_keys', 'auto');
        $disable = $policy === 'disable' || ($policy !== 'fail' && ($this->app->isLocal() || $this->app->runningUnitTests()));

        $this->warnOnce($driver, $disable);

        return $disable ? Mode::Disabled : Mode::Misconfigured;
    }

    /**
     * Whether the widget should be drawn.
     */
    public function isActive(?string $driver = null): bool
    {
        return $this->mode($driver) === Mode::Live;
    }

    public function verify(VerificationRequest $request, ?string $driver = null): VerificationResult
    {
        $driver ??= $this->defaultDriver();

        switch ($this->mode($driver)) {
            case Mode::Fake:
                return $this->fakePasses ? VerificationResult::passed() : VerificationResult::failed(Failure::Rejected, ['fake']);
            case Mode::Disabled:
                return VerificationResult::passed();
            case Mode::Misconfigured:
                return VerificationResult::failed(Failure::NotConfigured);
            case Mode::Live:
                break;
        }

        $instance = $this->driver($driver);
        $request = new VerificationRequest(
            $request->token,
            $request->ip,
            $request->action ?? $instance->defaultAction(),
            $request->minScore ?? $instance->defaultMinScore(),
            $request->hostnames,
        );

        if ($request->token === null || trim($request->token) === '') {
            return VerificationResult::failed(Failure::MissingToken);
        }

        $tokens = $this->app->make(VerifiedTokens::class);
        $key = hash('sha256', implode("\0", [$driver, $request->token, (string) $request->action, (string) $request->minScore, json_encode($request->hostnames)]));

        if (($known = $tokens->get($key)) !== null) {
            return $known;
        }

        $result = $instance->verify($request);

        if (!$result->success && $result->failure === Failure::Unavailable) {
            $this->log->warning('Captcha provider [{driver}] is unavailable: {codes}', ['driver' => $driver, 'codes' => implode(',', $result->errorCodes)]);

            if ((bool) config('filament-captcha.fail_open', false)) {
                $result = VerificationResult::passed();
            }
        }

        $tokens->put($key, $result);

        return $result;
    }

    public function rule(?string $driver = null): CaptchaRule
    {
        return new CaptchaRule($driver);
    }

    /**
     * Client-side configuration for the widget script.
     *
     * @param array{action?: ?string, theme?: ?string, size?: ?string, locale?: ?string} $options
     *
     * @return array<string, mixed>
     */
    public function clientConfig(?string $driver = null, array $options = []): array
    {
        $instance = $this->driver($driver);

        $options['theme'] ??= is_string($theme = config('filament-captcha.theme', 'auto')) ? $theme : 'auto';
        $options['locale'] ??= app()->getLocale();

        $refresh = config('filament-captcha.token_refresh', 100);

        return $instance->clientConfig($options) + ['refresh' => is_numeric($refresh) ? (int) $refresh : 100];
    }

    /**
     * Content-Security-Policy sources for one driver (or all the given ones), merged by directive.
     *
     * @param list<string>|null $drivers
     *
     * @return array<string, list<string>>
     */
    public function cspSources(?array $drivers = null): array
    {
        $merged = [];

        foreach ($drivers ?? [$this->defaultDriver()] as $name) {
            foreach ($this->driver($name)->cspSources() as $directive => $sources) {
                $merged[$directive] = array_values(array_unique([...($merged[$directive] ?? []), ...$sources]));
            }
        }

        return $merged;
    }

    private function warnFakeIgnored(): void
    {
        if (isset($this->warned['*fake'])) {
            return;
        }

        $this->warned['*fake'] = true;
        $this->log->warning('filament-captcha.fake is set outside local/testing and is IGNORED. Use Captcha::fake() in code if you really mean it.');
    }

    private function warnOnce(string $driver, bool $disabled): void
    {
        if (isset($this->warned[$driver])) {
            return;
        }

        $this->warned[$driver] = true;

        $message = $disabled
            ? 'Captcha driver [{driver}] has no site key / secret: the captcha is DISABLED and every form passes without one. Set the keys (see config/filament-captcha.php) before going live.'
            : 'Captcha driver [{driver}] has no site key / secret: validation FAILS until the keys are set (config/filament-captcha.php).';

        $this->log->warning($message, ['driver' => $driver]);
    }
}
