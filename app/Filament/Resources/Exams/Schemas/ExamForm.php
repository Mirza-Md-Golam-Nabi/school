<?php

namespace App\Filament\Resources\Exams\Schemas;

use App\Enums\ClassLevel;
use App\Enums\ExamNotificationChannel;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Support\AcademicSession;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('exam_type_id')
                    ->label('Exam Type')
                    ->options(fn () => ExamType::pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('class_id')
                    ->label('Class')
                    ->options(fn () => Classes::pluck('name', 'id'))
                    ->multiple(fn (string $operation): bool => $operation === 'create')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state, string $operation): void {
                        if ($operation === 'create') {
                            $set('session_year', self::currentSessionYearFor($state));
                        }
                    })
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (self::mixesDifferentCurrentSessions($value)) {
                            $fail('এখন কলেজ আর স্কুলের চলতি সেশন আলাদা — কলেজের (একাদশ-দ্বাদশ) ক্লাসগুলোর পরীক্ষা আলাদা করে তৈরি করুন।');
                        }
                    })
                    ->required(),

                Select::make('session_year')
                    ->label('Session Year')
                    ->options(fn (Get $get): array => AcademicSession::optionsFor(self::sessionLevelFor($get('class_id'))))
                    ->default(fn (): int => AcademicSession::currentYearFor())
                    ->required(),

                Toggle::make('is_published')
                    ->label('Published')
                    ->helperText('Published হলে students দেখতে পাবে')
                    ->default(false),

                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->before('end_date'),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->after('start_date'),

                Section::make('Notification Settings')
                    ->description('এই পরীক্ষার মার্ক ও রেজাল্ট কোন মাধ্যমে ছাত্র/অভিভাবককে যাবে। SMS যায় অভিভাবকের নম্বরে, আর SMS Settings-এ সেই event চালু থাকতে হয়।')
                    ->collapsible()
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        self::channelsField('subject_marks_channels', 'বিষয়ভিত্তিক মার্ক', [ExamNotificationChannel::Push])
                            ->helperText('বিষয়ের তালিকা থেকে "Send Marks" চাপলে এই মাধ্যমগুলো বাছাই করা থাকবে।'),

                        self::channelsField('result_channels', 'সম্পূর্ণ রেজাল্ট', [ExamNotificationChannel::Push, ExamNotificationChannel::Sms])
                            ->helperText('"Send Result" চাপলে বা publish করার সময় এই মাধ্যমগুলোতে যাবে।'),

                        Toggle::make('notify_result_on_publish')
                            ->label('Publish করার সময় রেজাল্ট নিজে থেকে পাঠাও')
                            ->helperText('বন্ধ রাখলে publish-এর পর "Send Result" চেপে নিজে পাঠাতে হবে। Rankings calculate করা না থাকলে শুধু "রেজাল্ট প্রকাশিত হয়েছে" push যাবে।')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  list<ExamNotificationChannel>  $defaults  Used while the exam has no choice stored.
     */
    private static function channelsField(string $name, string $label, array $defaults): CheckboxList
    {
        $defaultValues = array_map(fn (ExamNotificationChannel $channel): string => $channel->value, $defaults);

        return CheckboxList::make($name)
            ->label($label)
            ->options(collect(ExamNotificationChannel::cases())->mapWithKeys(fn (ExamNotificationChannel $channel): array => [$channel->value => $channel->getLabel()])->all())
            ->default($defaultValues)
            ->afterStateHydrated(function (CheckboxList $component, ?Exam $record) use ($name, $defaultValues): void {
                if ($record !== null && $record->getAttribute($name) === null) {
                    $component->state($defaultValues);
                }
            });
    }

    /**
     * The session running today for the selected class(es) — the first
     * selected class decides when several are picked.
     */
    public static function currentSessionYearFor(mixed $classIds): int
    {
        return AcademicSession::currentYearFor(self::selectedClasses($classIds)->first());
    }

    /**
     * College when every selected class is a college class, so the session
     * options read "2026-27"; otherwise school sessions.
     */
    private static function sessionLevelFor(mixed $classIds): ?ClassLevel
    {
        $classes = self::selectedClasses($classIds);

        return $classes->isNotEmpty() && $classes->every(fn (Classes $class): bool => $class->isCollege())
            ? ClassLevel::College
            : null;
    }

    /**
     * Between January and June the college session (started last July) and
     * the school session differ, so one session year cannot fit both.
     */
    private static function mixesDifferentCurrentSessions(mixed $classIds): bool
    {
        return self::selectedClasses($classIds)
            ->map(fn (Classes $class): int => $class->currentSessionYear())
            ->unique()
            ->count() > 1;
    }

    /**
     * @return Collection<int, Classes>
     */
    private static function selectedClasses(mixed $classIds): Collection
    {
        $classIds = array_filter((array) $classIds);

        return $classIds === [] ? new Collection : Classes::whereKey($classIds)->get();
    }
}
