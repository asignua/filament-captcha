<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Drivers;

use Asignua\FilamentCaptcha\Contracts\CaptchaDriver;
use Asignua\FilamentCaptcha\Enums\Failure;
use Asignua\FilamentCaptcha\VerificationRequest;
use Asignua\FilamentCaptcha\VerificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/**
 * Shared flow of the "POST secret + response, read JSON" providers (reCAPTCHA, Turnstile, hCaptcha).
 */
abstract class SiteVerifyDriver implements CaptchaDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly array $config,
        protected readonly Factory $http,
        protected readonly int $timeout = 5,
    ) {}

    abstract public function name(): string;

    abstract protected function endpoint(): string;

    public function siteKey(): string
    {
        return $this->string('site_key');
    }

    public function isConfigured(): bool
    {
        return $this->siteKey() !== '' && $this->string('secret_key') !== '';
    }

    public function defaultAction(): ?string
    {
        return null;
    }

    public function defaultMinScore(): ?float
    {
        return null;
    }

    /**
     * The provider echoes the action back, so it can be compared.
     */
    protected function checksAction(): bool
    {
        return false;
    }

    public function verify(VerificationRequest $request): VerificationResult
    {
        if ($request->token === null || trim($request->token) === '') {
            return VerificationResult::failed(Failure::MissingToken);
        }

        try {
            $response = $this->http
                ->asForm()
                ->timeout(max(1, $this->timeout))
                ->post($this->endpoint(), array_filter([
                    'secret' => $this->string('secret_key'),
                    'response' => $request->token,
                    'remoteip' => $request->ip,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        } catch (ConnectionException) {
            return VerificationResult::failed(Failure::Unavailable, ['connection-failed']);
        }

        $data = $response->json();

        if (!$response->successful() || !is_array($data)) {
            return VerificationResult::failed(Failure::Unavailable, ['http-'.$response->status()]);
        }

        $codes = $this->errorCodes($data);
        $score = isset($data['score']) && is_numeric($data['score']) ? (float) $data['score'] : null;
        $action = is_string($data['action'] ?? null) ? $data['action'] : null;
        $hostname = is_string($data['hostname'] ?? null) ? $data['hostname'] : null;

        if (($data['success'] ?? null) !== true) {
            return VerificationResult::failed(Failure::Rejected, $codes, $score, $action, $hostname);
        }

        if ($request->minScore !== null && $this->defaultMinScore() !== null && ($score ?? 0.0) < $request->minScore) {
            return VerificationResult::failed(Failure::LowScore, $codes, $score, $action, $hostname);
        }

        if ($request->action !== null && $this->checksAction() && $action !== $request->action) {
            return VerificationResult::failed(Failure::WrongAction, $codes, $score, $action, $hostname);
        }

        if ($request->hostnames !== null && !$this->hostnameAllowed($hostname, $request->hostnames)) {
            return VerificationResult::failed(Failure::WrongHostname, $codes, $score, $action, $hostname);
        }

        return VerificationResult::passed($score, $action, $hostname);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function clientConfig(array $options = []): array
    {
        return [
            'driver' => $this->name(),
            'siteKey' => $this->siteKey(),
            'action' => $options['action'] ?? $this->defaultAction(),
            'theme' => $options['theme'] ?? null,
            'size' => $options['size'] ?? null,
            'locale' => $this->locale($options['locale'] ?? null),
        ];
    }

    protected function string(string $key): string
    {
        $value = $this->config[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    protected function locale(mixed $locale): ?string
    {
        if (!is_string($locale) || $locale === '') {
            return null;
        }

        return str_replace('_', '-', $locale);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    private function errorCodes(array $data): array
    {
        $codes = $data['error-codes'] ?? [];

        if (!is_array($codes)) {
            return [];
        }

        return array_values(array_filter($codes, is_string(...)));
    }

    /**
     * @param list<string> $allowed
     */
    private function hostnameAllowed(?string $hostname, array $allowed): bool
    {
        if ($hostname === null) {
            return false;
        }

        foreach ($allowed as $candidate) {
            if (strcasecmp($candidate, $hostname) === 0) {
                return true;
            }
        }

        return false;
    }
}
