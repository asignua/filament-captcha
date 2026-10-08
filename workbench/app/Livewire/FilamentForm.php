<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A Livewire component with a Filament schema that contains the Captcha field.
 */
class FilamentForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    public ?array $saved = null;

    public static ?Captcha $field = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                static::$field ?? Captcha::make(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): View
    {
        return view('workbench::filament-form');
    }
}
