<?php

namespace App\Filament\Resources\ResultRules\Schemas;

use App\Enums\ResultRounding;
use App\Models\ExamType;
use App\Models\ResultRule;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ResultRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Rule')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Rule Name')
                            ->placeholder('যেমন: সেমিস্টার পদ্ধতি')
                            ->required()
                            ->maxLength(255),

                        Select::make('classes')
                            ->label('Classes')
                            ->helperText('একটা ক্লাস একটাই নিয়মে থাকতে পারে')
                            ->relationship('classes', 'name')
                            ->multiple()
                            ->preload()
                            ->required()
                            ->rules([
                                fn (?ResultRule $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $taken = ResultRule::classesTakenByOtherRules((array) $value, $record?->id);

                                    if ($taken->isNotEmpty()) {
                                        $fail('এই ক্লাসগুলো অন্য নিয়মে আছে: '.$taken->implode(', '));
                                    }
                                },
                            ]),

                        Select::make('target_exam_type_id')
                            ->label('Result Of')
                            ->helperText('যে পরীক্ষার রেজাল্ট এই নিয়মে তৈরি হবে — নিচের অংশগুলোর একটা হিসেবেও এটা থাকতে হবে')
                            ->relationship('targetExamType', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('full_marks')
                            ->label('Full Marks')
                            ->helperText('ফাঁকা রাখলে School Settings-এর পূর্ণমান')
                            ->placeholder(fn (): string => (string) ResultRule::make()->resolvedFullMarks())
                            ->numeric()
                            ->minValue(1),

                        Select::make('rounding')
                            ->label('Rounding')
                            ->helperText('শুধু চূড়ান্ত মার্কে রাউন্ড হয়, প্রতিটা অংশে আলাদা করে না')
                            ->options(ResultRounding::class)
                            ->default(ResultRounding::Decimal)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Components')
                    ->description('কোন পরীক্ষার কত % রেজাল্টে যাবে — সব মিলে ১০০% হতে হবে')
                    ->schema([
                        Repeater::make('components')
                            ->relationship()
                            ->hiddenLabel()
                            ->orderColumn('sort_order')
                            ->schema([
                                Select::make('exam_type_id')
                                    ->label('Exam Type')
                                    ->options(fn () => ExamType::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                TextInput::make('weight_percent')
                                    ->label('Share')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required(),

                                Toggle::make('must_pass')
                                    ->label('আলাদা পাস লাগবে')
                                    ->helperText('এই পরীক্ষায় বিষয়ের Pass Mark না পেলে বিষয়ে ফেল')
                                    ->inline(false),
                            ])
                            ->columns(3)
                            ->defaultItems(2)
                            ->minItems(1)
                            ->addActionLabel('Add Exam Type')
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $problem = ResultRule::componentsProblem((array) $value, $get('target_exam_type_id'));

                                    if ($problem) {
                                        $fail($problem);
                                    }
                                },
                            ]),
                    ]),
            ]);
    }
}
