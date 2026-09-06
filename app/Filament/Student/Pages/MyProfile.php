<?php

namespace App\Filament\Student\Pages;

use App\Enums\FeeDiscountType;
use App\Models\FeeStructure;
use App\Models\StudentFeeDiscount;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

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
        $profile = $this->getStudentProfile();

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
                    ->extraAttributes(['class' => 'fi-student-profile-tabs'])
                    ->tabs([
                        Tab::make('Basic Info')
                            ->icon(Heroicon::OutlinedUser)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema($this->basicInfoSchema($profile)),

                        Tab::make('Guardian Info')
                            ->icon(Heroicon::OutlinedUsers)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema($this->guardianInfoSchema()),

                        Tab::make('Fee Info')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->extraAttributes(['class' => 'text-xs sm:text-sm'])
                            ->schema($this->feeInfoSchema($profile)),
                    ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private function basicInfoSchema(StudentProfile $profile): array
    {
        return [
            Section::make('Account')
                ->schema([
                    TextEntry::make('user.name')->label('Name'),
                    TextEntry::make('user.email')->label('Email'),
                ])
                ->columns(2),

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
                ->columns(4),

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
                        ->view('filament.student.infolists.address-list'),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function guardianInfoSchema(): array
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
                ->columns(3),

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
                ->columns(3),

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
                ->columns(5),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function feeInfoSchema(StudentProfile $profile): array
    {
        $feeStructures = FeeStructure::query()
            ->where('class_id', $profile->current_class_id)
            ->where('session_year', $profile->session_year)
            ->where('is_active', true)
            ->with('feeType')
            ->get();

        $structureAmountsByFeeType = $feeStructures->pluck('amount', 'fee_type_id');

        $discounts = $profile->feeDiscounts()
            ->with(['feeType', 'discount'])
            ->where('session_year', $profile->session_year)
            ->get()
            ->map(fn (StudentFeeDiscount $studentDiscount): array => [
                'fee_type_name' => $studentDiscount->feeType?->name,
                'discount_name' => $studentDiscount->discount?->name,
                'amount_in_taka' => $this->calculateDiscountAmountInTaka($studentDiscount, $structureAmountsByFeeType),
            ]);

        return [
            Section::make('Current Fee Structure')
                ->description('Session '.$profile->session_year.' — '.($profile->class?->name ?? 'N/A'))
                ->schema([
                    ViewEntry::make('fee_structures')
                        ->hiddenLabel()
                        ->state($feeStructures)
                        ->view('filament.student.infolists.fee-structure'),
                ]),

            Section::make('Discounts Applied')
                ->schema([
                    ViewEntry::make('fee_discounts')
                        ->hiddenLabel()
                        ->state($discounts)
                        ->view('filament.student.infolists.fee-discounts'),
                ]),
        ];
    }

    /**
     * @param  Collection<int, float>  $structureAmountsByFeeType
     */
    private function calculateDiscountAmountInTaka(StudentFeeDiscount $studentDiscount, Collection $structureAmountsByFeeType): float
    {
        $discount = $studentDiscount->discount;

        if (! $discount) {
            return 0.0;
        }

        if ($discount->discount_type === FeeDiscountType::Percent) {
            $feeAmount = (float) ($structureAmountsByFeeType->get($studentDiscount->fee_type_id) ?? 0);

            return round($feeAmount * ((float) $discount->discount_value / 100), 1);
        }

        return round((float) $discount->discount_value, 1);
    }

    private function getStudentProfile(): ?StudentProfile
    {
        /** @var User $user */
        $user = auth()->user();

        return StudentProfile::where('user_id', $user->id)
            ->with(['user', 'class', 'section', 'group', 'addresses'])
            ->first();
    }
}
