<?php

namespace App\Filament\Pages;

use App\Actions\QuestionBank\ConnectToQuestionBankAction;
use App\Support\QuestionBankConnection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use UnitEnum;

/**
 * Connects this school to its School account in the Question Bank: the
 * computer operator copies the Question Bank's address, the connection
 * code and the secret from that account's "School software connection"
 * page, and "Save and connect" checks them with the Question Bank.
 *
 * @property-read Schema $form
 */
class QuestionBankSettings extends Page
{
    protected string $view = 'filament.pages.question-bank-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'School Settings';

    protected static ?string $slug = 'question-bank';

    public static function getNavigationLabel(): string
    {
        return __('Question Bank');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Question Bank');
    }

    public function mount(): void
    {
        $this->form->fill([
            'url' => QuestionBankConnection::url(),
            'code' => QuestionBankConnection::code(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Connection details'))
                    ->description(__('Copy these from your school account in the Question Bank: School software connection.'))
                    ->schema([
                        TextInput::make('url')
                            ->label(__('Question Bank address'))
                            ->placeholder('https://questionbank.example.com')
                            ->url()
                            ->required(),
                        TextInput::make('code')
                            ->label(__('Connection code'))
                            ->placeholder('QB-XXXXXXXXXX')
                            ->required(),
                        TextInput::make('secret')
                            ->label(__('Secret'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (): string => QuestionBankConnection::secret() !== null
                                ? __('A secret is saved. Leave this empty to keep it.')
                                : __('The secret is shown only on the Question Bank page — keep it to the school.'))
                            ->required(fn (): bool => QuestionBankConnection::secret() === null),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveAndConnect')
                ->label(__('Save and connect'))
                ->icon(Heroicon::OutlinedLink)
                ->action('saveAndConnect'),
        ];
    }

    public function saveAndConnect(ConnectToQuestionBankAction $connect): void
    {
        $data = $this->form->getState();

        QuestionBankConnection::save($data['url'], $data['code'], $data['secret'] ?? null);
        $this->form->fill(['url' => QuestionBankConnection::url(), 'code' => QuestionBankConnection::code()]);

        try {
            $schoolName = $connect->handle();
        } catch (RuntimeException $exception) {
            Notification::make()->title(__('Not connected'))->body($exception->getMessage())->danger()->send();

            return;
        }

        Log::info('Connected to the Question Bank.', ['url' => QuestionBankConnection::url(), 'by' => auth()->id()]);

        Notification::make()->title(__('Connected to the Question Bank as :school.', ['school' => $schoolName]))->success()->send();
    }
}
