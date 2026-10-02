<?php

namespace App\Filament\Pages;

use App\Enums\StudentIdCardField;
use App\Enums\StudentIdCardValidity;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Support\StudentIdCardLayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class StudentIdCards extends Page
{
    protected string $view = 'filament.pages.student-id-cards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Student ID Card';

    protected static ?string $title = 'Student ID Card';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::withCount([
            'studentProfiles as active_students_count' => fn ($q) => $q->where('status', StudentStatus::Active),
        ])
            ->active()
            ->orderBy('order')
            ->get();
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
                    $this->cardFieldsCheckboxList('front_fields', 'front_order', 'Front Side'),
                    $this->cardFieldsOrderRepeater('front_order', 'Front Side Order'),
                    $this->cardFieldsCheckboxList('back_fields', 'back_order', 'Back Side'),
                    $this->cardFieldsOrderRepeater('back_order', 'Back Side Order'),
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

    /**
     * The checkboxes decide which fields a side prints. Ticking or unticking
     * one keeps that side's order list in step: a newly ticked field joins the
     * end, an unticked one drops out, and the rest keep their arranged order.
     */
    private function cardFieldsCheckboxList(string $name, string $orderName, string $label): CheckboxList
    {
        $limit = StudentIdCardLayout::MAX_FIELDS_PER_SIDE;

        return CheckboxList::make($name)
            ->label($label)
            ->options(StudentIdCardField::options())
            ->descriptions(StudentIdCardField::descriptions())
            ->columns(['default' => 2, 'sm' => 3])
            ->rules(['array', "max:{$limit}"])
            ->validationMessages(['max' => "কার্ডের এক পাশে সর্বোচ্চ {$limit}টা তথ্য রাখা যায়।"])
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, ?array $state) use ($orderName): void {
                $selected = $state ?? [];

                $order = collect($get($orderName) ?? [])
                    ->filter(fn (array $item): bool => in_array($item['field'] ?? null, $selected, true));

                foreach (array_diff($selected, $order->pluck('field')->all()) as $newlySelected) {
                    $order->put((string) Str::uuid(), ['field' => $newlySelected]);
                }

                $set($orderName, $order->all());
            });
    }

    /**
     * The selected fields of one side as a drag-to-reorder list — the card
     * prints them top to bottom in this order.
     */
    private function cardFieldsOrderRepeater(string $name, string $label): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->helperText('টেনে বা তীর চিহ্নে চেপে উপরে-নিচে সরান — কার্ডে এই সিরিয়ালেই ছাপা হবে।')
            ->schema([
                Select::make('field')
                    ->hiddenLabel()
                    ->options(StudentIdCardField::options())
                    ->selectablePlaceholder(false)
                    ->disabled()
                    ->dehydrated(),
            ])
            ->addable(false)
            ->deletable(false)
            ->reorderableWithButtons()
            ->hidden(fn (?array $state): bool => blank($state));
    }

    /**
     * @param  array<int, StudentIdCardField>  $fields
     * @return array<int, array{field: string}>
     */
    private function orderItems(array $fields): array
    {
        return array_map(fn (StudentIdCardField $field): array => ['field' => $field->value], $fields);
    }

    /**
     * The ticked fields arranged as the order list has them. A ticked field
     * the order list somehow lacks is appended rather than silently dropped.
     *
     * @param  array<int, string>  $selected
     * @param  array<int|string, array{field?: string}>  $order
     * @return array<int, string>
     */
    private function orderedSelection(array $selected, array $order): array
    {
        $arranged = array_intersect(array_column($order, 'field'), $selected);

        return array_values(array_unique([...$arranged, ...$selected]));
    }
}
