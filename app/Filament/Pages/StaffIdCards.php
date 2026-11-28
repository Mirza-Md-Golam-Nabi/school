<?php

namespace App\Filament\Pages;

use App\Actions\BuildStaffIdCardsPdfAction;
use App\Enums\Permissions\ProfilePermission;
use App\Enums\StaffIdCardField;
use App\Filament\Concerns\ConfiguresIdCardFields;
use App\Filament\Concerns\PicksIdCardDesign;
use App\Filament\Concerns\RequiresPermission;
use App\Models\StaffProfile;
use App\Support\StaffIdCardLayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class StaffIdCards extends Page
{
    use ConfiguresIdCardFields;
    use PicksIdCardDesign;
    use RequiresPermission;

    protected static function requiredPermission(): BackedEnum
    {
        return ProfilePermission::VIEW_STAFF_PROFILES;
    }

    protected string $view = 'filament.pages.staff-id-cards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Staff ID Card';

    protected static ?string $title = 'Staff ID Card';

    /**
     * @return class-string<StaffIdCardLayout>
     */
    protected function idCardLayout(): string
    {
        return StaffIdCardLayout::class;
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    public function staffMembers(): Collection
    {
        return StaffProfile::with('user')
            ->active()
            ->orderedByUserName()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'staffMembers' => $this->staffMembers(),
            ...$this->designPickerViewData(
                BuildStaffIdCardsPdfAction::CARD_WIDTH,
                BuildStaffIdCardsPdfAction::CARD_HEIGHT,
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        $options = StaffIdCardField::options();
        $limit = StaffIdCardLayout::MAX_FIELDS_PER_SIDE;

        return [
            Action::make('configureCardFields')
                ->label('Card Fields')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading('Staff ID Card Fields')
                ->modalDescription('কার্ডের সামনে ও পেছনে কোন কোন তথ্য ছাপা হবে তা বেছে নিন। ছবি, নাম, স্কুলের তথ্য, ID নম্বর ও স্বাক্ষর সবসময় থাকে। কোনো staff-এর যে তথ্য নেই, সেটা তার কার্ডে বাদ যায়।')
                ->modalSubmitActionLabel('Save')
                ->fillForm(fn (): array => [
                    'front_fields' => array_column(StaffIdCardLayout::frontFields(), 'value'),
                    'front_order' => $this->orderItems(StaffIdCardLayout::frontFields()),
                    'back_fields' => array_column(StaffIdCardLayout::backFields(), 'value'),
                    'back_order' => $this->orderItems(StaffIdCardLayout::backFields()),
                    'issue_date' => StaffIdCardLayout::issueDate()->toDateString(),
                    'show_qr_code' => StaffIdCardLayout::showsQrCode(),
                ])
                ->schema([
                    $this->cardFieldsCheckboxList('front_fields', 'front_order', 'Front Side', $options, $limit),
                    $this->cardFieldsOrderRepeater('front_order', 'Front Side Order', $options),
                    $this->cardFieldsCheckboxList('back_fields', 'back_order', 'Back Side', $options, $limit),
                    $this->cardFieldsOrderRepeater('back_order', 'Back Side Order', $options),
                    DatePicker::make('issue_date')
                        ->label('Issue Date')
                        ->helperText(fn (): string => 'এই তারিখটাই কার্ডে ছাপা হবে, যেমন 01 JAN '.now()->year)
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->required(),
                    $this->cardQrCodeToggle(),
                ])
                ->action(function (array $data): void {
                    StaffIdCardLayout::save(
                        $this->orderedSelection($data['front_fields'] ?? [], $data['front_order'] ?? []),
                        $this->orderedSelection($data['back_fields'] ?? [], $data['back_order'] ?? []),
                        Carbon::parse($data['issue_date']),
                    );

                    StaffIdCardLayout::saveShowsQrCode((bool) ($data['show_qr_code'] ?? true));

                    Notification::make()
                        ->title('Staff ID card fields saved.')
                        ->success()
                        ->send();
                }),

            Action::make('viewAll')
                ->label('View All')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(route('staff-id-cards.view'), shouldOpenInNewTab: true)
                ->visible(fn (): bool => StaffIdCardLayout::hasSubmittedDesign() && StaffProfile::query()->active()->exists()),

            Action::make('downloadAll')
                ->label('Download All')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('staff-id-cards.download'))
                ->visible(fn (): bool => StaffIdCardLayout::hasSubmittedDesign() && StaffProfile::query()->active()->exists()),
        ];
    }
}
