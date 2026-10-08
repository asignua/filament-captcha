<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Fixtures;

use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A second factor that every user has and that accepts any code: enough to drive Filament's
 * two-step login (password, then challenge) in a test.
 */
class AlwaysOnMultiFactor implements MultiFactorAuthenticationProvider
{
    public function isEnabled(Authenticatable $user): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'always';
    }

    public function getLoginFormLabel(): string
    {
        return 'Always';
    }

    public function getManagementSchemaComponents(): array
    {
        return [];
    }

    public function getChallengeFormComponents(Authenticatable $user): array
    {
        return [TextInput::make('code')];
    }
}
