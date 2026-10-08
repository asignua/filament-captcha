<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\View\Components;

use Asignua\FilamentCaptcha\CaptchaManager;
use Asignua\FilamentCaptcha\Enums\Mode;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Js;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;

/**
 * <x-filament-captcha::widget wire:model="captchaToken" />
 *
 * The challenge for a plain Livewire component (Filament forms use the Captcha field instead). Needs
 * <x-filament-captcha::scripts /> once in the layout.
 */
class Widget extends Component
{
    public function __construct(
        public ?string $driver = null,
        public ?string $action = null,
        public ?string $theme = null,
        public ?string $size = null,
        public ?string $locale = null,
        public ?string $statePath = null,
        public string $name = 'captcha_token',
    ) {}

    /**
     * A closure, because the attribute bag (wire:model) is attached to the component only AFTER render() runs;
     * the closure gets it in $data.
     */
    public function render(): Closure
    {
        return function (array $data): View {
            $manager = app(CaptchaManager::class);
            $mode = $manager->mode($this->driver);

            $attributes = $data['attributes'] ?? null;
            $model = $this->statePath ?? ($attributes instanceof ComponentAttributeBag ? $attributes->wire('model')->value() : null);
            $model = is_string($model) && $model !== '' ? $model : null;

            $config = $mode === Mode::Live
                ? $manager->clientConfig($this->driver, array_filter([
                    'action' => $this->action,
                    'theme' => $this->theme,
                    'size' => $this->size,
                    'locale' => $this->locale,
                ], static fn (?string $value): bool => $value !== null)) + [
                    'nonce' => Vite::cspNonce(),
                    'messages' => [
                        'loadFailed' => __('filament-captcha::filament-captcha.widget.load_failed'),
                        'failed' => __('filament-captcha::filament-captcha.widget.failed'),
                    ],
                ]
                : [];

            $state = $model !== null ? '$wire.$entangle('.Js::from($model)->toHtml().')' : "''";

            /** @var view-string $view */
            $view = 'filament-captcha::components.widget';

            return view($view, $data + [
                'mode' => $mode,
                'model' => $model,
                'xData' => 'filamentCaptcha({ config: '.Js::from($config)->toHtml().', state: '.$state.', statePath: '.Js::from($model)->toHtml().' })',
            ]);
        };
    }
}
