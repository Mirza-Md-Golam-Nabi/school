<?php

namespace App\Filament\Teacher\Pages;

use App\Models\TeacherProfile;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MyProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'My Profile';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return 'My Profile';
    }

    public function content(Schema $schema): Schema
    {
        $profile = $this->getTeacherProfile();

        if (! $profile) {
            return $schema->components([
                TextEntry::make('empty')
                    ->hiddenLabel()
                    ->state('No profile information found.'),
            ]);
        }

        return $schema
            ->record($profile)
            ->components([
                Tabs::make('Profile')
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'fi-teacher-profile-tabs'])
                    ->tabs([
                        Tab::make('Basic Info')
                            ->icon(Heroicon::OutlinedUser)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema($this->basicInfoSchema($profile)),

                        Tab::make('Professional Info')
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema($this->professionalInfoSchema()),
                    ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private function basicInfoSchema(TeacherProfile $profile): array
    {
        return [
            Section::make('Account')
                ->schema([
                    TextEntry::make('user.name')->label('Name'),
                    TextEntry::make('user.email')->label('Email'),
                ])
                ->columns(2),

            Section::make('Personal')
                ->schema([
                    TextEntry::make('gender')->label('Gender')->badge(),
                    TextEntry::make('date_of_birth')->label('Date of Birth')->date('d M Y')->placeholder('—'),
                    TextEntry::make('blood_group')->label('Blood Group')->badge()->placeholder('—'),
                    TextEntry::make('religion')->label('Religion')->badge()->placeholder('—'),
                    TextEntry::make('nationality')->label('Nationality'),
                ])
                ->columns(3),

            Section::make('Address')
                ->schema([
                    ViewEntry::make('addresses')
                        ->hiddenLabel()
                        ->state($profile->addresses)
                        ->view('filament.teacher.infolists.address-list'),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function professionalInfoSchema(): array
    {
        return [
            Section::make('Professional')
                ->schema([
                    TextEntry::make('designation')->label('Designation')->placeholder('—'),
                    TextEntry::make('department')->label('Department')->placeholder('—'),
                    TextEntry::make('qualification')->label('Qualification')->placeholder('—'),
                    TextEntry::make('joining_date')->label('Joining Date')->date('d M Y')->placeholder('—'),
                    TextEntry::make('status')->label('Status')->badge(),
                ])
                ->columns(3),
        ];
    }

    private function getTeacherProfile(): ?TeacherProfile
    {
        /** @var User $user */
        $user = auth()->user();

        return TeacherProfile::where('user_id', $user->id)
            ->with(['user', 'addresses'])
            ->first();
    }
}
