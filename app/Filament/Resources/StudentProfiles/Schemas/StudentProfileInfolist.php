<?php

namespace App\Filament\Resources\StudentProfiles\Schemas;

use App\Models\StudentProfile;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StudentProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ViewEntry::make('profile_header')
                    ->hiddenLabel()
                    ->view('filament.resources.student-profiles.infolists.profile-header'),

                Tabs::make('Student')
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'fi-student-profile-tabs'])
                    ->tabs([
                        Tab::make('Basic Info')
                            ->icon(Heroicon::OutlinedUser)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema(self::basicInfoSchema()),

                        Tab::make('Guardian Info')
                            ->icon(Heroicon::OutlinedUsers)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema(self::guardianInfoSchema()),

                        Tab::make('Address')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema(self::addressSchema()),

                        Tab::make('Fee Summary')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema(self::feeSummarySchema()),

                        Tab::make('Subjects')
                            ->icon(Heroicon::OutlinedBookOpen)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema(self::subjectsSchema()),
                    ]),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    private static function basicInfoSchema(): array
    {
        return [
            Section::make('Academic')
                ->schema([
                    TextEntry::make('roll_no')->label('Roll No'),
                    TextEntry::make('registration_no')->label('Registration No')->placeholder('—'),
                    TextEntry::make('session_year')->label('Session Year'),
                    TextEntry::make('class.name')->label('Class')->placeholder('—'),
                    TextEntry::make('section.name')->label('Section')->placeholder('—'),
                    TextEntry::make('group.name')->label('Group')->placeholder('—'),
                    TextEntry::make('admission_date')->label('Admission Date')->date('d M Y')->placeholder('—'),
                    TextEntry::make('status')->label('Status')->badge(),
                ])
                ->columns(['default' => 2, 'sm' => 3, 'lg' => 4]),

            Section::make('Personal')
                ->schema([
                    TextEntry::make('user.email')->label('Email')->placeholder('—'),
                    TextEntry::make('gender')->label('Gender')->badge(),
                    TextEntry::make('date_of_birth')->label('Date of Birth')->date('d M Y')->placeholder('—'),
                    TextEntry::make('blood_group')->label('Blood Group')->badge()->placeholder('—'),
                    TextEntry::make('religion')->label('Religion')->badge()->placeholder('—'),
                    TextEntry::make('nationality')->label('Nationality')->placeholder('—'),
                    TextEntry::make('birth_certificate_no')->label('Birth Certificate No')->placeholder('—'),
                ])
                ->columns(['default' => 2, 'sm' => 3, 'lg' => 4]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function guardianInfoSchema(): array
    {
        return [
            Section::make("Father's Information")
                ->schema([
                    ImageEntry::make('father_photo')
                        ->hiddenLabel()
                        ->disk('public')
                        ->circular()
                        ->imageSize(80),
                    TextEntry::make('father_name')->label('Name')->placeholder('—'),
                    TextEntry::make('father_occupation')->label('Occupation')->placeholder('—'),
                ])
                ->columns(['default' => 1, 'sm' => 3]),

            Section::make("Mother's Information")
                ->schema([
                    ImageEntry::make('mother_photo')
                        ->hiddenLabel()
                        ->disk('public')
                        ->circular()
                        ->imageSize(80),
                    TextEntry::make('mother_name')->label('Name')->placeholder('—'),
                    TextEntry::make('mother_occupation')->label('Occupation')->placeholder('—'),
                ])
                ->columns(['default' => 1, 'sm' => 3]),

            Section::make("Guardian's Information")
                ->schema([
                    ImageEntry::make('guardian_photo')
                        ->hiddenLabel()
                        ->disk('public')
                        ->circular()
                        ->imageSize(80),
                    TextEntry::make('guardian_name')->label('Name')->placeholder('—'),
                    TextEntry::make('guardian_relation')->label('Relation')->placeholder('—'),
                    TextEntry::make('guardian_occupation')->label('Occupation')->placeholder('—'),
                    TextEntry::make('guardian_phone')->label('Phone')->placeholder('—'),
                ])
                ->columns(['default' => 1, 'sm' => 3, 'lg' => 5]),
        ];
    }

    /**
     * @return array<int, ViewEntry>
     */
    private static function addressSchema(): array
    {
        return [
            ViewEntry::make('addresses')
                ->hiddenLabel()
                ->state(fn (StudentProfile $record) => $record->addresses)
                ->view('filament.student.infolists.address-list'),
        ];
    }

    /**
     * @return array<int, ViewEntry>
     */
    private static function feeSummarySchema(): array
    {
        return [
            ViewEntry::make('fee_summary')
                ->hiddenLabel()
                ->view('filament.resources.student-profiles.infolists.fee-summary'),
        ];
    }

    /**
     * @return array<int, ViewEntry>
     */
    private static function subjectsSchema(): array
    {
        return [
            ViewEntry::make('subjects')
                ->hiddenLabel()
                ->view('filament.resources.student-profiles.infolists.subjects-list'),
        ];
    }
}
