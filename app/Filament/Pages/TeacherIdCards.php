<?php

namespace App\Filament\Pages;

use App\Actions\BuildTeacherIdCardsPdfAction;
use App\Enums\TeacherIdCardField;
use App\Filament\Concerns\ConfiguresIdCardFields;
use App\Filament\Concerns\PicksIdCardDesign;
use App\Models\TeacherProfile;
use App\Support\TeacherIdCardLayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class TeacherIdCards extends Page
{
    use ConfiguresIdCardFields;
    use PicksIdCardDesign;

    protected string $view = 'filament.pages.teacher-id-cards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Teacher ID Card';

    protected static ?string $title = 'Teacher ID Card';

    /**
     * @return class-string<TeacherIdCardLayout>
     */
    protected function idCardLayout(): string
    {
        return TeacherIdCardLayout::class;
    }

    /**
     * @return Collection<int, TeacherProfile>
     */
    public function teachers(): Collection
    {
        return TeacherProfile::with('user')
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
            'teachers' => $this->teachers(),
            ...$this->designPickerViewData(
                BuildTeacherIdCardsPdfAction::CARD_WIDTH,
                BuildTeacherIdCardsPdfAction::CARD_HEIGHT,
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        $options = TeacherIdCardField::options();
        $limit = TeacherIdCardLayout::MAX_FIELDS_PER_SIDE;

        return [
            Action::make('configureCardFields')
                ->label('Card Fields')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading('Teacher ID Card Fields')
                ->modalDescription('কার্ডের সামনে ও পেছনে কোন কোন তথ্য ছাপা হবে তা বেছে নিন। ছবি, নাম, স্কুলের তথ্য, barcode ও স্বাক্ষর সবসময় থাকে। কোনো teacher-এর যে তথ্য নেই, সেটা তার কার্ডে বাদ যায়।')
                ->modalSubmitActionLabel('Save')
                ->fillForm(fn (): array => [
                    'front_fields' => array_column(TeacherIdCardLayout::frontFields(), 'value'),
                    'front_order' => $this->orderItems(TeacherIdCardLayout::frontFields()),
                    'back_fields' => array_column(TeacherIdCardLayout::backFields(), 'value'),
                    'back_order' => $this->orderItems(TeacherIdCardLayout::backFields()),
                    'issue_date' => TeacherIdCardLayout::issueDate()->toDateString(),
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
                ])
                ->action(function (array $data): void {
                    TeacherIdCardLayout::save(
                        $this->orderedSelection($data['front_fields'] ?? [], $data['front_order'] ?? []),
                        $this->orderedSelection($data['back_fields'] ?? [], $data['back_order'] ?? []),
                        Carbon::parse($data['issue_date']),
                    );

                    Notification::make()
                        ->title('Teacher ID card fields saved.')
                        ->success()
                        ->send();
                }),

            Action::make('viewAll')
                ->label('View All')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(route('teacher-id-cards.view'), shouldOpenInNewTab: true)
                ->visible(fn (): bool => TeacherIdCardLayout::hasSubmittedDesign() && TeacherProfile::query()->active()->exists()),

            Action::make('downloadAll')
                ->label('Download All')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('teacher-id-cards.download'))
                ->visible(fn (): bool => TeacherIdCardLayout::hasSubmittedDesign() && TeacherProfile::query()->active()->exists()),
        ];
    }
}
