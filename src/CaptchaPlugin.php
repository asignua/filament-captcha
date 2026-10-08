<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha;

use Asignua\FilamentCaptcha\Pages\Login;
use Asignua\FilamentCaptcha\Pages\Register;
use Asignua\FilamentCaptcha\Pages\RequestPasswordReset;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class CaptchaPlugin implements Plugin
{
    /** @var array<string, array{enabled: bool, driver: ?string}> */
    protected array $pages = [
        'login' => ['enabled' => false, 'driver' => null],
        'register' => ['enabled' => false, 'driver' => null],
        'password_reset' => ['enabled' => false, 'driver' => null],
    ];

    protected bool $loadScripts = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'asignua-filament-captcha';
    }

    /**
     * Add the captcha to the panel's login page. Call ->plugin() AFTER ->login(): the plugin swaps in its
     * own page class. With a custom page, use the AddsCaptchaField trait there instead.
     */
    public function login(bool $condition = true, ?string $driver = null): static
    {
        $this->pages['login'] = ['enabled' => $condition, 'driver' => $driver];

        return $this;
    }

    public function registration(bool $condition = true, ?string $driver = null): static
    {
        $this->pages['register'] = ['enabled' => $condition, 'driver' => $driver];

        return $this;
    }

    public function passwordReset(bool $condition = true, ?string $driver = null): static
    {
        $this->pages['password_reset'] = ['enabled' => $condition, 'driver' => $driver];

        return $this;
    }

    /**
     * Whether the plugin prints the client script on every panel page (default). Turn off if you load
     * <x-filament-captcha::scripts /> yourself.
     */
    public function loadScripts(bool $condition = true): static
    {
        $this->loadScripts = $condition;

        return $this;
    }

    /**
     * Driver for an auth page scope (login, register, password_reset); null = the config default.
     */
    public function driverFor(string $scope): ?string
    {
        return $this->pages[$scope]['driver'] ?? null;
    }

    public function register(Panel $panel): void
    {
        if ($this->pages['login']['enabled']) {
            $panel->login(Login::class);
        }

        if ($this->pages['register']['enabled']) {
            $panel->registration(Register::class);
        }

        if ($this->pages['password_reset']['enabled']) {
            $panel->passwordReset(RequestPasswordReset::class);
        }

        if ($this->loadScripts) {
            $panel->renderHook(PanelsRenderHook::SCRIPTS_BEFORE, static fn (): string => Blade::render('<x-filament-captcha::scripts />'));
        }
    }

    public function boot(Panel $panel): void {}
}
