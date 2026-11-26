<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\ExamSubjectMerge;
use App\Models\Group;
use App\Models\Subject;
use App\Models\SubjectMerge;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The form fields and table columns shared by a class's subject merges
 * (rules may be left to School Settings) and an exam's own copy (every rule
 * filled in).
 */
class SubjectMergeFields
{
    /**
     * @param  Closure(): HasMany  $siblingMerges  the class's / exam's merges
     * @return array<int, mixed>
     */
    public static function form(int $classId, Closure $siblingMerges, bool $rulesRequired): array
    {
        $classSubjectOptions = Subject::taughtInClass($classId)
            ->ordered()
            ->get()
            ->pluck('display_name', 'id')
            ->all();

        $subjectOptions = fn (string $otherPaperField): Closure => fn (Get $get, ?Model $record): array => array_diff_key(
            $classSubjectOptions,
            array_flip([
                ...self::mergedSubjectIds($siblingMerges, $record),
                ...array_filter([(int) $get($otherPaperField)]),
            ]),
        );

        $defaults = SubjectMerge::defaultRules();
        $rulePlaceholder = fn (string $rule): ?string => $rulesRequired ? null : 'School default — '.$defaults[$rule]->getLabel();

        return [
            Grid::make(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->placeholder('যেমন: Bangla')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('name_bn')
                        ->label('Name (Bangla)')
                        ->placeholder('যেমন: বাংলা')
                        ->maxLength(255),
                    Select::make('first_subject_id')
                        ->label('1st Paper')
                        ->options($subjectOptions('second_subject_id'))
                        ->searchable()
                        ->live()
                        ->required()
                        ->rules([
                            fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $classId, $siblingMerges): void {
                                $taken = $siblingMerges()
                                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                    ->get()
                                    ->flatMap(fn (SubjectMerge|ExamSubjectMerge $merge): array => $merge->subjectIds());

                                $problem = SubjectMerge::mergeProblem($classId, $value, $get('second_subject_id'), $taken);

                                if ($problem) {
                                    $fail($problem);
                                }
                            },
                        ]),
                    Select::make('second_subject_id')
                        ->label('2nd Paper')
                        ->options($subjectOptions('first_subject_id'))
                        ->searchable()
                        ->live()
                        ->required()
                        ->helperText(fn (Get $get): ?string => self::groupMismatchWarning($classId, $get('first_subject_id'), $get('second_subject_id'))),
                ]),
            Section::make('Rules')
                ->description($rulesRequired ? null : 'ফাঁকা রাখলে School Settings-এর নিয়ম খাটবে')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('pass_rule')
                        ->label('Pass Rule')
                        ->options(MergedPassRule::class)
                        ->placeholder($rulePlaceholder('pass_rule'))
                        ->default($rulesRequired ? $defaults['pass_rule'] : null)
                        ->required($rulesRequired),
                    Select::make('component_pass_rule')
                        ->label('MCQ / Written / Practical Pass')
                        ->helperText('শুধু যৌথ নম্বরে পাশের নিয়মে খাটে')
                        ->options(MergedComponentPassRule::class)
                        ->placeholder($rulePlaceholder('component_pass_rule'))
                        ->default($rulesRequired ? $defaults['component_pass_rule'] : null)
                        ->required($rulesRequired),
                    Select::make('absent_rule')
                        ->label('Absent In One Paper')
                        ->options(MergedAbsentRule::class)
                        ->placeholder($rulePlaceholder('absent_rule'))
                        ->default($rulesRequired ? $defaults['absent_rule'] : null)
                        ->required($rulesRequired),
                    Select::make('rounding')
                        ->label('Rounding')
                        ->options(ResultRounding::class)
                        ->placeholder($rulePlaceholder('rounding'))
                        ->default($rulesRequired ? $defaults['rounding'] : null)
                        ->required($rulesRequired),
                ]),
        ];
    }

    /**
     * A note when some group of the class takes only one of the two papers
     * (Arabic 1st for every group, Arabic 2nd only for Humanities): the merge
     * does not apply to that group's students, who have the paper they take
     * counted on its own. Only informs — the merge can still be saved.
     */
    private static function groupMismatchWarning(int $classId, mixed $firstSubjectId, mixed $secondSubjectId): ?string
    {
        if (blank($firstSubjectId) || blank($secondSubjectId)) {
            return null;
        }

        $class = Classes::with('groups')->find($classId);
        $records = ClassGroupSubject::recordsBySubjectForClass($classId);
        $groups = $class?->has_group ? $class->groups : collect();

        $groupNames = $groups
            ->filter(function (Group $group) use ($records, $firstSubjectId, $secondSubjectId): bool {
                $takesFirst = ClassGroupSubject::isTakenByGroup($records->get((int) $firstSubjectId), (int) $group->id);
                $takesSecond = ClassGroupSubject::isTakenByGroup($records->get((int) $secondSubjectId), (int) $group->id);

                return $takesFirst !== $takesSecond;
            })
            ->pluck('name');

        if ($groupNames->isEmpty()) {
            return null;
        }

        return '⚠ '.$groupNames->implode(', ').' group-এর student-রা এই দুটো পত্রের একটাই পড়ে — তাদের জন্য এই মার্জ খাটবে না, পত্রটা আলাদা বিষয় হিসেবে গোনা হবে।';
    }

    /**
     * Subjects already used as a paper in another merge, leaving out the
     * merge being edited so its own papers stay selectable.
     *
     * @param  Closure(): HasMany  $siblingMerges
     * @return array<int, int>
     */
    private static function mergedSubjectIds(Closure $siblingMerges, ?Model $record): array
    {
        return $siblingMerges()
            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
            ->get()
            ->flatMap(fn (SubjectMerge|ExamSubjectMerge $merge): array => $merge->subjectIds())
            ->map(fn (mixed $subjectId): int => (int) $subjectId)
            ->all();
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        $ruleColumn = fn (string $rule, string $label): TextColumn => TextColumn::make($rule)
            ->label($label)
            ->badge()
            ->formatStateUsing(fn (mixed $state): string => str($state?->getLabel() ?? '')->before(' —')->toString())
            ->placeholder('School default')
            ->extraAttributes(['class' => ResponsiveText::CLASSES]);

        return [
            TextColumn::make('name')
                ->label('Subject')
                ->formatStateUsing(fn (SubjectMerge|ExamSubjectMerge $record): string => $record->display_name)
                ->weight('bold')
                ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            TextColumn::make('papers')
                ->label('Papers')
                ->state(fn (SubjectMerge|ExamSubjectMerge $record): string => ($record->firstSubject?->display_name ?? '—').' + '.($record->secondSubject?->display_name ?? '—'))
                ->wrap()
                ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            $ruleColumn('pass_rule', 'Pass'),
            $ruleColumn('component_pass_rule', 'MCQ / Written')->toggleable(isToggledHiddenByDefault: true),
            $ruleColumn('absent_rule', 'Absent'),
            $ruleColumn('rounding', 'Rounding')->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
