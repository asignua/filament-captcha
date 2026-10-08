<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha;

final readonly class VerificationRequest
{
    /**
     * @param list<string>|null $hostnames allowed hostnames; null = do not check
     */
    public function __construct(
        public ?string $token,
        public ?string $ip = null,
        public ?string $action = null,
        public ?float $minScore = null,
        public ?array $hostnames = null,
    ) {}
}
