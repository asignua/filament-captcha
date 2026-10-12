<x-filament-panels::page>
    <form wire:submit="send" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" style="margin-top: 1.5rem">Send request</x-filament::button>
    </form>
</x-filament-panels::page>
