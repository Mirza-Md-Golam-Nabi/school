<?php

namespace App\Filament\Resources\StudentProfiles\Concerns;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;

trait HasStudentCredentialsModal
{
    public function studentCredentialsAction(): Action
    {
        return Action::make('studentCredentials')
            ->label('Student Login Credentials')
            ->modalHeading('Student Login তৈরি হয়েছে')
            ->modalDescription('এই Student ID ও password সংরক্ষণ করে student-কে দিন। Student ID পরিবর্তনযোগ্য নয়; প্রথম লগইনেই student-কে password পরিবর্তন করতে হবে।')
            ->modalWidth(Width::Large)
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('student_id')
                                ->label('Student ID')
                                ->state(fn ($livewire): string => $livewire->getMountedAction()?->getArguments()['student_id'] ?? '')
                                ->copyable()
                                ->copyMessage('Student ID copied!')
                                ->icon('heroicon-o-identification')
                                ->weight('bold'),

                            TextEntry::make('password')
                                ->label('Password')
                                ->state(fn ($livewire): string => $livewire->getMountedAction()?->getArguments()['password'] ?? '')
                                ->copyable()
                                ->copyMessage('Password copied!')
                                ->icon('heroicon-o-key')
                                ->weight('bold')
                                ->color('danger'),
                        ]),
                    ])
                    ->compact(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }
}
