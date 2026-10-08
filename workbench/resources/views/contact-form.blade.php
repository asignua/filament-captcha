<form wire:submit="submit">
    <textarea wire:model="message"></textarea>
    @error('message') <p>{{ $message }}</p> @enderror

    <x-filament-captcha::widget wire:model="captchaToken" action="contact" />
    @error('captchaToken') <p class="captcha-error">{{ $message }}</p> @enderror

    <button type="submit">Send</button>
</form>
