<?php

namespace App\Filament\Resources\Notices\Schemas;

use App\Actions\Sms\SendNoticeSmsAction;
use App\Enums\NoticeTargetType;
use App\Enums\SmsEvent;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\SmsSetting;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class NoticeForm
{
    private static function resolveTargetType(Get $get): ?NoticeTargetType
    {
        $value = $get('target_type');

        if ($value instanceof NoticeTargetType) {
            return $value;
        }

        return NoticeTargetType::tryFrom((string) ($value ?? ''));
    }

    /**
     * When SMS is switched on, shows how many people it reaches, what it
     * costs, and whether the school's SMS balance covers it.
     */
    private static function smsHelperText(Get $get): string
    {
        if (! $get('send_sms')) {
            return 'নোটিশ প্রকাশের সময় প্রাপকদের SMS যাবে।';
        }

        $estimate = app(SendNoticeSmsAction::class)->estimate(
            self::resolveTargetType($get),
            (array) ($get('target_ids') ?? []),
            $get('title'),
            $get('body'),
        );

        $text = sprintf(
            'প্রাপক %d জন · %d SMS পার্ট · খরচ ৳%s · ব্যালেন্স ৳%s',
            $estimate['recipients'],
            $estimate['parts'],
            number_format($estimate['cost'], 2),
            number_format($estimate['balance'], 2),
        );

        return $estimate['is_affordable'] ? $text : $text.' — ব্যালেন্স অপর্যাপ্ত, SMS যাবে না।';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Notice Content')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        RichEditor::make('body')
                            ->required()
                            ->columnSpanFull()
                            ->extraInputAttributes(['style' => 'min-height: 150px;'])
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'bulletList', 'orderedList',
                                'link', 'blockquote',
                                'undo', 'redo',
                            ]),
                    ]),

                Section::make('Target Audience')
                    ->columns(2)
                    ->schema([
                        Select::make('target_type')
                            ->label('Send To')
                            ->options(NoticeTargetType::class)
                            ->default(NoticeTargetType::All->value)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => [
                                $set('target_ids', []),
                                $set('class_filter_id', null),
                            ]),

                        // Class filter — only for IndividualStudent to narrow down students
                        Select::make('class_filter_id')
                            ->label('Filter by Class')
                            ->options(Classes::orderBy('order', 'asc')->pluck('name', 'id')->toArray())
                            ->placeholder('All classes')
                            ->nullable()
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn (Set $set) => $set('target_ids', []))
                            ->visible(fn (Get $get): bool => self::resolveTargetType($get) === NoticeTargetType::IndividualStudent),

                        Select::make('target_ids')
                            ->label(fn (Get $get): string => match (self::resolveTargetType($get)) {
                                NoticeTargetType::ByClass => 'Select Classes',
                                NoticeTargetType::IndividualStudent => 'Select Students',
                                NoticeTargetType::IndividualTeacher => 'Select Teachers',
                                default => 'Targets',
                            })
                            ->options(fn (Get $get): array => match (self::resolveTargetType($get)) {
                                NoticeTargetType::ByClass => Classes::orderBy('order', 'asc')
                                    ->pluck('name', 'id')
                                    ->toArray(),

                                NoticeTargetType::IndividualStudent => StudentProfile::with('user')
                                    ->active()
                                    ->when(
                                        $get('class_filter_id'),
                                        fn ($q, $classId) => $q->where('current_class_id', $classId)
                                    )
                                    ->orderBy('roll_no')
                                    ->get()
                                    ->mapWithKeys(fn ($s) => [
                                        $s->user_id => $s->user->name.' (Roll: '.$s->roll_no.')',
                                    ])
                                    ->toArray(),

                                NoticeTargetType::IndividualTeacher => User::where('user_type', UserType::Teacher)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray(),

                                default => [],
                            })
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::resolveTargetType($get)?->requiresTargets() ?? false)
                            ->required(fn (Get $get): bool => self::resolveTargetType($get)?->requiresTargets() ?? false),
                    ]),

                Section::make('Publishing')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('published_at')
                            ->label('Schedule At')
                            ->hint('Leave empty to publish immediately. Set a future date to schedule.')
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now())
                            ->nullable(),

                        Toggle::make('send_sms')
                            ->label('Send SMS')
                            ->helperText(fn (Get $get): string => self::smsHelperText($get))
                            ->default(false)
                            ->live()
                            ->inline(false)
                            ->visible(fn (): bool => SmsSetting::current()->isEventEnabled(SmsEvent::Notice)),
                    ]),
            ]);
    }
}
