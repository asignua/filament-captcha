@use('Asignua\FilamentCaptcha\Enums\Mode')

@if ($mode === Mode::Live)
    <div
        {{ $attributes->whereDoesntStartWith('wire:model')->class(['fi-captcha', 'filament-captcha']) }}
        wire:ignore
        x-data="{!! $xData !!}"
    >
        <div x-ref="widget"></div>

        @unless ($model)
            <input type="hidden" name="{{ $name }}" x-model="state" />
        @endunless

        <p x-show="error" x-text="error" class="fi-fo-field-wrp-error-message filament-captcha__error" role="alert"></p>

        <noscript>{{ __('filament-captcha::filament-captcha.widget.noscript') }}</noscript>
    </div>
@elseif ($mode === Mode::Misconfigured)
    <p {{ $attributes->whereDoesntStartWith('wire:model')->class(['fi-fo-field-wrp-error-message', 'filament-captcha__error']) }} role="alert">
        {{ __('filament-captcha::filament-captcha.errors.not_configured') }}
    </p>
@endif
