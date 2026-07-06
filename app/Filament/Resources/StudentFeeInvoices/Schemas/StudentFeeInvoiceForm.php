<?php

namespace App\Filament\Resources\StudentFeeInvoices\Schemas;

use App\Enums\FeeDiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentFeeDiscount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StudentFeeInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Invoice Details')
                    ->columns(2)
                    ->schema([
                        Select::make('class_id_filter')
                            ->label('Class')
                            ->options(fn () => Classes::active()->orderBy('order')->pluck('name', 'id'))
                            ->default(fn () => request()->integer('class_id') ?: null)
                            ->live()
                            ->dehydrated(false)
                            ->native(false)
                            ->afterStateUpdated(fn (Set $set) => $set('student_id', null)),

                        Select::make('student_id')
                            ->label('Student')
                            ->options(fn (Get $get) => StudentProfile::with('user')
                                ->where('status', StudentStatus::Active)
                                ->when(
                                    $get('class_id_filter'),
                                    fn ($q, $classId) => $q->where('current_class_id', $classId)
                                )
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->id => $s->user->name.' (Roll: '.$s->roll_no.')'])
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::autoFillAmounts($get, $set);
                                self::checkDuplicate($get, $set);
                            }),

                        Select::make('fee_type_id')
                            ->label('Fee Type')
                            ->options(FeeType::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::autoFillAmounts($get, $set);
                                self::checkDuplicate($get, $set);
                            }),

                        TextInput::make('month')
                            ->label('Month (1–12)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->nullable()
                            ->live(onBlur: true)
                            ->helperText('Leave empty for one-time fees')
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::checkDuplicate($get, $set)),

                        TextInput::make('year')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->default(now()->year)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::checkDuplicate($get, $set)),

                        // Tracks duplicate state — not saved to DB
                        Hidden::make('has_duplicate')
                            ->default(false)
                            ->dehydrated(false),

                        Callout::make('duplicate_notice')
                            ->danger()
                            ->icon('heroicon-o-exclamation-circle')
                            ->heading('Duplicate Invoice')
                            ->description('An invoice already exists for this student, fee type, and month/year. Creating another is not allowed.')
                            ->visible(fn (Get $get): bool => (bool) $get('has_duplicate'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Amount Breakdown')
                    ->columns(2)
                    ->schema([
                        TextInput::make('original_amount')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('৳')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateNetAmount($get, $set)),

                        TextInput::make('discount_amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('৳')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateNetAmount($get, $set)),

                        TextInput::make('fine_amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('৳')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateNetAmount($get, $set)),

                        TextInput::make('waiver_amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('৳')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateNetAmount($get, $set)),

                        TextInput::make('net_amount')
                            ->label('Net Payable (৳)')
                            ->numeric()
                            ->prefix('৳')
                            ->readOnly()
                            ->default(0)
                            ->helperText('Auto-calculated: Original − Discount + Fine − Waiver'),

                        Select::make('status')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Unpaid)
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Waiver Info')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('waiver_by')
                            ->label('Waived By')
                            ->relationship('waivedBy', 'name')
                            ->searchable()
                            ->nullable()
                            ->native(false),
                        Textarea::make('waiver_reason')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function autoFillAmounts(Get $get, Set $set): void
    {
        $studentId = $get('student_id');
        $feeTypeId = $get('fee_type_id');

        if (! $studentId || ! $feeTypeId) {
            return;
        }

        $student = StudentProfile::find($studentId);
        if (! $student) {
            return;
        }

        // Original amount from fee structure
        $feeStructure = FeeStructure::where('class_id', $student->current_class_id)
            ->where('fee_type_id', $feeTypeId)
            ->where('is_active', true)
            ->first();

        $originalAmount = (float) ($feeStructure?->amount ?? 0);

        // Discount amount from student fee discount
        $studentDiscount = StudentFeeDiscount::where('student_id', $studentId)
            ->where('fee_type_id', $feeTypeId)
            ->with('discount')
            ->latest()
            ->first();

        $discountAmount = 0.0;
        if ($studentDiscount?->discount) {
            $disc = $studentDiscount->discount;
            $discountAmount = $disc->discount_type === FeeDiscountType::Percent
                ? round($originalAmount * ($disc->discount_value / 100), 2)
                : min((float) $disc->discount_value, $originalAmount);
        }

        $set('original_amount', $originalAmount);
        $set('discount_amount', $discountAmount);
        $set('fine_amount', 0);
        $set('waiver_amount', 0);
        $set('net_amount', max(0, $originalAmount - $discountAmount));
    }

    private static function checkDuplicate(Get $get, Set $set): void
    {
        $studentId = $get('student_id');
        $feeTypeId = $get('fee_type_id');
        $year = $get('year');

        if (! $studentId || ! $feeTypeId || ! $year) {
            $set('has_duplicate', false);

            return;
        }

        $month = $get('month') ?: null;

        $exists = StudentFeeInvoice::where('student_id', $studentId)
            ->where('fee_type_id', $feeTypeId)
            ->where('year', (int) $year)
            ->where('month', $month ? (int) $month : null)
            ->exists();

        $set('has_duplicate', $exists);
    }

    private static function recalculateNetAmount(Get $get, Set $set): void
    {
        $original = (float) ($get('original_amount') ?? 0);
        $discount = (float) ($get('discount_amount') ?? 0);
        $fine = (float) ($get('fine_amount') ?? 0);
        $waiver = (float) ($get('waiver_amount') ?? 0);

        $set('net_amount', max(0, $original - $discount + $fine - $waiver));
    }
}
