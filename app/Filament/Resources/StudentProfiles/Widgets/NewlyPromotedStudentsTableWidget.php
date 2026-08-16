<?php

namespace App\Filament\Resources\StudentProfiles\Widgets;

use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Filament\Resources\StudentProfiles\Tables\StudentProfilesTable;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NewlyPromotedStudentsTableWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    public int $classId = 0;

    public int $awaitingSessionYear = 0;

    public function table(Table $table): Table
    {
        return StudentProfilesTable::configure(
            $table->query(
                StudentProfileResource::getEloquentQuery()
                    ->where('current_class_id', $this->classId)
                    ->where('session_year', '>', $this->awaitingSessionYear)
            )
        )->heading('নতুন উত্তীর্ণ হওয়া Students');
    }
}
