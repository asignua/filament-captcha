<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Support;

use Asignua\FilamentCaptcha\VerificationResult;

/**
 * Per-request memory of verified tokens. A token is single-use at the provider, but Livewire may run the
 * same rule twice in one request (validateOnly, then validate); the second call must see the first answer
 * instead of a "timeout-or-duplicate" rejection. Bound as a scoped singleton: never shared between requests.
 */
final class VerifiedTokens
{
    /** @var array<string, VerificationResult> */
    private array $results = [];

    public function get(string $key): ?VerificationResult
    {
        return $this->results[$key] ?? null;
    }

    public function put(string $key, VerificationResult $result): void
    {
        $this->results[$key] = $result;
    }
}
