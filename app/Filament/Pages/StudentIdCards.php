<?php

namespace App\Filament\Pages;

use App\Actions\BuildClassStudentIdCardsPdfAction;
use App\Enums\StudentIdCardField;
use App\Enums\StudentIdCardValidity;
use App\Enums\StudentStatus;
use App\Filament\Concerns\ConfiguresIdCardFields;
use App\Filament\Concerns\PicksIdCardDesign;
use App\Models\Classes;
use App\Support\StudentIdCardLayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class StudentIdCards extends Page
{
    use ConfiguresIdCardFields;
    use PicksIdCardDesign;

    protected string $view = 'filament.pages.student-id-cards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Student ID Card';

    protected static ?string $title = 'Student ID Card';

    /**
     * Active classes with their active student counts — read fresh on every
     * render, since a Livewire round trip would drop the counted column.
     *
     * @return Collection<int, Classes>
     */
    public function classes(): Collection
    {
        return Classes::withCount([
            'studentProfiles as active_students_count' => fn ($q) => $q->where('status', StudentStatus::Active),
        ])
            ->active()
            ->orderBy('order')
            ->get();
    }

    /**
     * @return class-string<StudentIdCardLayout>
     */
    protected function idCardLayout(): string
    {
        return StudentIdCardLayout::class;
    }

    /**
     * Every design with its artwork and palette, for the picker's previews.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'classes' => $this->classes(),
            ...$this->designPickerViewData(
                BuildClassStudentIdCardsPdfAction::CARD_WIDTH,
                BuildClassStudentIdCardsPdfAction::CARD_HEIGHT,
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configureCardFields')
                ->label('Card Fields')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->modalHeading('ID Card Fields')
                ->modalDescription('কার্ডের সামনে ও পেছনে কোন কোন তথ্য ছাপা হবে তা বেছে নিন। ছবি, নাম, স্কুলের তথ্য, barcode ও স্বাক্ষর সবসময় থাকে। কোনো student-এর যে তথ্য নেই, সেটা তার কার্ডে বাদ যায়।')
                ->modalSubmitActionLabel('Save')
                ->fillForm(fn (): array => [
                    'front_fields' => array_column(StudentIdCardLayout::frontFields(), 'value'),
                    'front_order' => $this->orderItems(StudentIdCardLayout::frontFields()),
                    'back_fields' => array_column(StudentIdCardLayout::backFields(), 'value'),
                    'back_order' => $this->orderItems(StudentIdCardLayout::backFields()),
                    'validity' => StudentIdCardLayout::validity()->value,
                    'issue_date' => StudentIdCardLayout::issueDate()->toDateString(),
                ])
                ->schema([
                    $this->cardFieldsCheckboxList('front_fields', 'front_order', 'Front Side', StudentIdCardField::options(), StudentIdCardLayout::MAX_FIELDS_PER_SIDE, StudentIdCardField::descriptions()),
                    $this->cardFieldsOrderRepeater('front_order', 'Front Side Order', StudentIdCardField::options()),
                    $this->cardFieldsCheckboxList('back_fields', 'back_order', 'Back Side', StudentIdCardField::options(), StudentIdCardLayout::MAX_FIELDS_PER_SIDE, StudentIdCardField::descriptions()),
                    $this->cardFieldsOrderRepeater('back_order', 'Back Side Order', StudentIdCardField::options()),
                    Radio::make('validity')
                        ->label('Validity')
                        ->options(StudentIdCardValidity::options())
                        ->descriptions(StudentIdCardValidity::descriptions())
                        ->live()
                        ->required(),
                    DatePicker::make('issue_date')
                        ->label('Issue Date')
                        ->helperText(fn (): string => 'এই তারিখটাই কার্ডে ছাপা হবে, যেমন 01 JAN '.now()->year)
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->visible(fn (Get $get): bool => $get('validity') === StudentIdCardValidity::IssueDate->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    StudentIdCardLayout::save(
                        $this->orderedSelection($data['front_fields'] ?? [], $data['front_order'] ?? []),
                        $this->orderedSelection($data['back_fields'] ?? [], $data['back_order'] ?? []),
                        StudentIdCardValidity::from($data['validity']),
                        filled($data['issue_date'] ?? null) ? Carbon::parse($data['issue_date']) : null,
                    );

                    Notification::make()
                        ->title('ID card fields saved.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
