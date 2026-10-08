## Filament Captcha (asignua/filament-captcha)

- Filament form: `Asignua\FilamentCaptcha\Forms\Components\Captcha::make()` (`->driver()`, `->captchaAction()`, `->minScore()`, `->hostnames()`). It attaches its own validation rule; do not add one, and never hide it with `->visible()` (hidden fields are not validated).
- Plain Livewire: `use InteractsWithCaptcha;`, `<x-filament-captcha::widget wire:model="captchaToken" action="contact" />`, `$this->validateCaptcha(action: 'contact')`; `<x-filament-captcha::scripts />` once in the layout (panels with the plugin get it automatically).
- Anywhere else: `'captcha' => [Asignua\FilamentCaptcha\Facades\Captcha::rule()]` (implicit, empty token fails).
- Drivers: `recaptcha_v2`, `recaptcha_v2_invisible`, `recaptcha_v3`, `turnstile`, `hcaptcha`. Config `config/filament-captcha.php`, keys `CAPTCHA_<DRIVER>_SITE_KEY` / `_SECRET_KEY`. v2 checkbox and invisible need different key pairs.
- Auth pages: `CaptchaPlugin::make()->login()->registration()->passwordReset()`, called after `->login()` on the panel.
- Tests: `Captcha::fake()` (passes) or `Captcha::fake(passes: false)`; otherwise `Http::fake()` the provider's siteverify URL.
- Missing keys: disabled with a log warning in local/testing, FAILS validation elsewhere.
