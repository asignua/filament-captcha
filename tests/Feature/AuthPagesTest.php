<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Tests\Feature;

use Asignua\FilamentCaptcha\Pages\Login;
use Asignua\FilamentCaptcha\Pages\Register;
use Asignua\FilamentCaptcha\Pages\RequestPasswordReset;
use Asignua\FilamentCaptcha\Tests\TestCase;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Workbench\App\Models\User;

class AuthPagesTest extends TestCase
{
    private const string TURNSTILE = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();

        auth()->logout();
        Filament::setCurrentPanel('admin');
    }

    public function test_the_plugin_swaps_in_the_auth_pages(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(Login::class, $panel->getLoginRouteAction());
        $this->assertSame(Register::class, $panel->getRegistrationRouteAction());
        $this->assertSame(RequestPasswordReset::class, $panel->getRequestPasswordResetRouteAction());
    }

    public function test_the_login_page_shows_the_widget(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('filamentCaptcha(', false)
            ->assertSee('/filament-captcha/captcha.js?v=', false);
    }

    public function test_login_is_refused_without_a_valid_captcha_even_with_the_right_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        Http::fake([self::TURNSTILE => Http::response(['success' => false])]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-pass', 'captcha' => 'bad'])
            ->call('authenticate')
            ->assertHasErrors(['data.captcha']);

        $this->assertGuest();
    }

    public function test_login_works_with_a_valid_captcha(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'action' => 'login'])]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-pass', 'captcha' => 'good'])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_action_name_is_enforced_for_turnstile(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        Http::fake([self::TURNSTILE => Http::response(['success' => true, 'action' => 'register'])]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-pass', 'captcha' => 'good'])
            ->call('authenticate')
            ->assertHasErrors(['data.captcha']);
    }

    public function test_registration_and_password_reset_pages_have_the_field(): void
    {
        Http::fake([self::TURNSTILE => Http::response(['success' => false])]);

        Livewire::test(Register::class)
            ->fillForm(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'password-123', 'passwordConfirmation' => 'password-123', 'captcha' => 'bad'])
            ->call('register')
            ->assertHasErrors(['data.captcha']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'ann@example.com', 'captcha' => 'bad'])
            ->call('request')
            ->assertHasErrors(['data.captcha']);

        $this->assertDatabaseMissing('users', ['email' => 'ann@example.com']);
    }
}
