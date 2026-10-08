<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Facades;

use Asignua\FilamentCaptcha\CaptchaManager;
use Asignua\FilamentCaptcha\Contracts\CaptchaDriver;
use Asignua\FilamentCaptcha\Enums\Mode;
use Asignua\FilamentCaptcha\Rules\CaptchaRule;
use Asignua\FilamentCaptcha\VerificationRequest;
use Asignua\FilamentCaptcha\VerificationResult;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static CaptchaDriver driver(?string $name = null)
 * @method static CaptchaManager extend(string $name, Closure $factory)
 * @method static CaptchaManager fake(bool $passes = true)
 * @method static CaptchaManager unfake()
 * @method static Mode mode(?string $driver = null)
 * @method static bool isActive(?string $driver = null)
 * @method static VerificationResult verify(VerificationRequest $request, ?string $driver = null)
 * @method static CaptchaRule rule(?string $driver = null)
 * @method static array<string, mixed> clientConfig(?string $driver = null, array<string, mixed> $options = [])
 * @method static array<string, list<string>> cspSources(?array<int, string> $drivers = null)
 *
 * @see CaptchaManager
 */
class Captcha extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CaptchaManager::class;
    }
}
