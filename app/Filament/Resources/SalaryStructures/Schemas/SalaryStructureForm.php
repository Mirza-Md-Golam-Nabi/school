<?php

namespace App\Filament\Resources\SalaryStructures\Schemas;

use App\Filament\Resources\Concerns\HasProfileableFields;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SalaryComponent;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SalaryStructureForm
{
    public static function configure(Schema $schema): Schema
    {
        [$profileableType, $profileableId] = HasProfileableFields::fields();

        return $schema
            ->columns(2)
            ->components([
                Section::make('Assignment')
                    ->columns(2)
                    ->schema([
                        $profileableType,
                        $profileableId,
                        DatePicker::make('effective_from')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        DatePicker::make('effective_to')
                            ->label('Effective To')
                            ->helperText('বেতন পরিবর্তন হলে খালি রাখুন — নতুন structure তৈরি করুন')
                            ->native(false)
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                    ]),

                Section::make('Salary Setup')
                    ->columns(2)
                    ->schema([
                        Toggle::make('use_components')
                            ->label('Component-wise breakdown ব্যবহার করবেন?')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('flat_amount')
                            ->label('Flat Amount')
                            ->numeric()
                            ->prefix('৳')
                            ->required(fn (Get $get): bool => ! $get('use_components'))
                            ->visible(fn (Get $get): bool => ! $get('use_components'))
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),

                        Repeater::make('components')
                            ->relationship()
                            ->label('Salary Components')
                            ->schema([
                                Select::make('salary_component_id')
                                    ->label('Component')
                                    ->options(fn () => SalaryComponent::where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn (SalaryComponent $component) => [
                                            $component->id => $component->name.' ('.$component->type->getLabel().')',
                                        ]))
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextInput::make('amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('৳')
                                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add Component')
                            ->visible(fn (Get $get): bool => (bool) $get('use_components'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
