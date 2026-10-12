<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Asignua\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A form with the Captcha field, for the README screenshots.
 *
 * @property Schema $form
 */
class RequestQuote extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected string $view = 'workbench::request-quote';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request a quote')
                    ->description('Tell us what you need and we will reply within one working day.')
                    ->schema([
                        TextInput::make('company')->required(),
                        TextInput::make('email')->email()->required(),
                        Textarea::make('details')->rows(3)->required(),
                        Captcha::make(),
                    ]),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $this->form->getState();

        Notification::make()->title('Request sent')->success()->send();
    }
}
