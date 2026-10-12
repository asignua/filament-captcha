<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Asignua\FilamentCaptcha\CaptchaPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Filament\Pages\RequestQuote;
use Workbench\App\Models\User;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'workbench');

        // Cloudflare's dummy tokens carry no `action`, so the login form's action check rejects them; the
        // screenshot script signs in through this shortcut instead.
        Route::middleware('web')->get('/demo-login', function () {
            Auth::login(User::query()->firstOrFail());

            return redirect('/admin/request-quote');
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Acme Supply')
            ->pages([RequestQuote::class])
            ->login()
            ->registration()
            ->passwordReset()
            ->plugin(CaptchaPlugin::make()->login()->registration()->passwordReset())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
