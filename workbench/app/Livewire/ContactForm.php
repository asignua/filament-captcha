<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentCaptcha\Concerns\InteractsWithCaptcha;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A plain Livewire component (no Filament) with the captcha widget.
 */
class ContactForm extends Component
{
    use InteractsWithCaptcha;

    public string $message = '';

    public bool $sent = false;

    public function submit(): void
    {
        $this->validate(['message' => ['required', 'string', 'max:500']]);
        $this->validateCaptcha(action: 'contact');

        $this->sent = true;
    }

    public function render(): View
    {
        return view('workbench::contact-form');
    }
}
