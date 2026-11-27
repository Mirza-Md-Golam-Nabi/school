<?php

namespace App\Filament\Pages;

use App\Enums\Permissions\SmsPermission;
use App\Enums\SmsEvent;
use App\Enums\SmsLanguage;
use App\Filament\Concerns\HasSmsPermission;
use App\Filament\Resources\SmsRechargeRequests\SmsRechargeRequestResource;
use App\Models\SmsSetting;
use App\Support\Sms\SmsSegments;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class SmsSettings extends Page
{
    use HasSmsPermission;

    protected string $view = 'filament.pages.sms-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'SMS';

    protected static ?string $navigationLabel = 'SMS Settings';

    protected static ?string $title = 'SMS Settings';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static function smsPermission(): SmsPermission
    {
        return SmsPermission::MANAGE_SMS_SETTINGS;
    }

    public function mount(): void
    {
        $setting = SmsSetting::current();
        $enabled = $setting->enabled_events ?? [];

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'language' => $setting->smsLanguage()->value,
            'events' => collect(SmsEvent::cases())->mapWithKeys(fn (SmsEvent $event): array => [$event->value => in_array($event->value, $enabled, true)])->all(),
            'templates' => collect(SmsEvent::cases())->mapWithKeys(fn (SmsEvent $event): array => [$event->value => $setting->template($event)])->all(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recharge')
                ->label('Recharge Request')
                ->icon(Heroicon::OutlinedBanknotes)
                ->url(SmsRechargeRequestResource::getUrl('index')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('SMS Balance')
                        ->icon(Heroicon::OutlinedWallet)
                        ->description('স্কুলের নিজস্ব SMS ব্যালেন্স। রিচার্জ করতে "Recharge Request" দিন।')
                        ->schema([
                            Grid::make(['default' => 1, 'sm' => 3])->schema([
                                TextEntry::make('balance_display')
                                    ->label('Current Balance')
                                    ->state(fn (): string => '৳'.number_format(SmsSetting::current()->currentBalance(), 2))
                                    ->weight('bold')
                                    ->color('success'),
                                TextEntry::make('sms_count_display')
                                    ->label('SMS Available (approx.)')
                                    ->state(fn (): string => number_format(SmsSetting::current()->smsCountFor()).' টি SMS')
                                    ->helperText('ইংরেজিতে ১৬০ অক্ষর / বাংলায় ৭০ অক্ষর = ১ টি SMS।'),
                                TextEntry::make('rate_per_sms_display')
                                    ->label('Charge per SMS (৳)')
                                    ->state(fn (): string => '৳'.rtrim(rtrim(number_format(SmsSetting::current()->ratePerSms(), 4), '0'), '.'))
                                    ->helperText('NextFusion থেকে নির্ধারিত — এখান থেকে বদলানো যায় না।')
                                    ->weight('bold'),
                            ]),
                        ]),

                    Section::make('SMS Service')
                        ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                        ->schema([
                            Toggle::make('is_enabled')
                                ->label('Enable SMS')
                                ->helperText('বন্ধ থাকলে কোনো ঘটনাতেই SMS যাবে না।'),
                            Select::make('language')
                                ->label('SMS Language')
                                ->options(SmsLanguage::class)
                                ->required()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(fn (Set $set, Get $get, mixed $old, mixed $state) => $this->switchDefaultTemplates($set, $get, $old, $state))
                                ->helperText('ডিফল্ট টেমপ্লেট এই ভাষায় থাকবে। ভাষা বদলালে যে টেমপ্লেট আপনি নিজে বদলাননি সেগুলো নতুন ভাষায় বদলে যাবে; নিজে লেখা টেমপ্লেট যেমন আছে তেমনই থাকবে। ইংরেজিতে ১ SMS = ১৬০ অক্ষর, বাংলায় ৭০ — ইংরেজিতে খরচ কম।'),
                        ]),

                    Section::make('Events & Templates')
                        ->icon(Heroicon::OutlinedBellAlert)
                        ->description('কোন কোন ঘটনায় SMS যাবে আর কী লেখা যাবে। {name} এর মতো placeholder পাঠানোর সময় আসল তথ্য দিয়ে বদলে যায়। টেমপ্লেট ফাঁকা রাখলে ডিফল্ট লেখা ব্যবহার হবে।')
                        ->schema(collect(SmsEvent::cases())->map(fn (SmsEvent $event): Section => $this->eventSection($event))->all()),
                ])->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save Settings')
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    private function eventSection(SmsEvent $event): Section
    {
        return Section::make($event->getLabel())
            ->description($event->description())
            ->compact()
            ->collapsible()
            ->schema([
                Toggle::make("events.{$event->value}")
                    ->label('Send SMS for this event')
                    ->live(),
                Textarea::make("templates.{$event->value}")
                    ->label('Template')
                    ->rows(3)
                    ->maxLength(1000)
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => (bool) $get("events.{$event->value}"))
                    ->helperText(fn (?string $state): string => sprintf(
                        'Placeholders: %s · placeholder ছাড়া এই লেখা ≈ %d টি SMS',
                        implode(', ', array_keys($event->placeholders())),
                        SmsSegments::count((string) $state),
                    )),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = SmsSetting::current();
        $templates = $setting->templates ?? [];
        $language = self::toSmsLanguage($state['language'] ?? null);

        foreach (SmsEvent::cases() as $event) {
            if (array_key_exists($event->value, $state['templates'] ?? [])) {
                $template = trim((string) $state['templates'][$event->value]);

                // An untouched default is stored empty, so it keeps following the default when that changes.
                $templates[$event->value] = $template === $event->defaultTemplate($language) ? '' : $template;
            }
        }

        $setting->fill([
            'is_enabled' => (bool) ($state['is_enabled'] ?? false),
            'enabled_events' => collect($state['events'] ?? [])->filter()->keys()->values()->all(),
            'templates' => $templates,
            'language' => $language,
        ]);

        $setting->save();

        Notification::make()
            ->success()
            ->title('SMS settings saved')
            ->send();
    }

    /**
     * When the SMS language changes, every template still showing the old
     * language's default switches to the new language's default; templates
     * the school wrote itself are kept.
     */
    private function switchDefaultTemplates(Set $set, Get $get, mixed $old, mixed $state): void
    {
        $previousLanguage = self::toSmsLanguage($old);
        $language = self::toSmsLanguage($state);

        foreach (SmsEvent::cases() as $event) {
            $template = trim((string) $get("templates.{$event->value}"));

            if ($template === '' || $template === $event->defaultTemplate($previousLanguage)) {
                $set("templates.{$event->value}", $event->defaultTemplate($language));
            }
        }
    }

    /**
     * The Select may hand over the enum or its raw value.
     */
    private static function toSmsLanguage(mixed $language): SmsLanguage
    {
        return $language instanceof SmsLanguage ? $language : (SmsLanguage::tryFrom((string) $language) ?? SmsLanguage::Bangla);
    }
}
