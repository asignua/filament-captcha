<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha;

use Asignua\FilamentCaptcha\Enums\Failure;

final readonly class VerificationResult
{
    /**
     * @param list<string> $errorCodes the provider's `error-codes`
     */
    public function __construct(
        public bool $success,
        public ?Failure $failure = null,
        public array $errorCodes = [],
        public ?float $score = null,
        public ?string $action = null,
        public ?string $hostname = null,
    ) {}

    public static function passed(?float $score = null, ?string $action = null, ?string $hostname = null): self
    {
        return new self(true, null, [], $score, $action, $hostname);
    }

    /**
     * @param list<string> $errorCodes
     */
    public static function failed(Failure $failure, array $errorCodes = [], ?float $score = null, ?string $action = null, ?string $hostname = null): self
    {
        return new self(false, $failure, $errorCodes, $score, $action, $hostname);
    }
}
