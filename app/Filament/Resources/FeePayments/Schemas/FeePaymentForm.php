<?php

namespace App\Filament\Resources\FeePayments\Schemas;

use App\Enums\PaymentMethod;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class FeePaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Payment Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('receipt_no')
                            ->label('Receipt No.')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'RCP-'.strtoupper(uniqid()))
                            ->columnSpan(1),
                        DatePicker::make('payment_date')
                            ->required()
                            ->default(now())
                            ->native(false),
                        TextInput::make('class_id_filter')
                            ->label('Class')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Hidden::make('filter_class_id')
                            ->dehydrated(false),

                        Select::make('student_type')
                            ->label('Student Type')
                            ->options([
                                'current' => 'Current Students',
                                'former' => 'Former Students',
                            ])
                            ->default('current')
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn (Set $set) => $set('student_id', null))
                            ->columnSpanFull(),

                        Select::make('student_id')
                            ->label('Student')
                            ->options(function (Get $get) {
                                $classId = $get('filter_class_id');
                                $type = $get('student_type') ?? 'current';

                                if ($type === 'former') {
                                    if (! $classId) {
                                        return [];
                                    }

                                    return StudentProfile::with('user')
                                        ->former()
                                        ->where('current_class_id', $classId)
                                        ->get()
                                        ->mapWithKeys(fn ($s) => [
                                            $s->id => $s->user->name.' ('.($s->status->getLabel()).')',
                                        ]);
                                }

                                return StudentProfile::with('user')
                                    ->active()
                                    ->when($classId, fn ($q) => $q->where('current_class_id', $classId))
                                    ->get()
                                    ->mapWithKeys(fn ($s) => [$s->id => $s->user->name.' (Roll: '.$s->roll_no.')']);
                            })
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('invoice_ids', []);
                                $set('amount_paid', null);
                            })
                            ->columnSpanFull(),

                        View::make('filament.resources.fee-payments.invoice-summary')
                            ->viewData(fn (Get $get) => [
                                'invoices' => ($studentId = $get('student_id'))
                                    ? StudentFeeInvoice::with(['feeType', 'payments'])
                                        ->where('student_id', $studentId)
                                        ->payable()
                                        ->orderBy('year')
                                        ->orderByRaw('COALESCE(month, 13)')
                                        ->get()
                                    : collect(),
                            ])
                            ->columnSpanFull(),

                        Select::make('invoice_ids')
                            ->label('Select Invoices to Pay')
                            ->multiple()
                            ->options(function (Get $get) {
                                $studentId = $get('student_id');
                                if (! $studentId) {
                                    return [];
                                }

                                // Always include already-selected invoices (e.g. paid ones in edit mode)
                                $selected = array_filter((array) ($get('invoice_ids') ?? []));

                                return StudentFeeInvoice::with(['feeType', 'payments'])
                                    ->where('student_id', $studentId)
                                    ->where(fn ($q) => $q->payable()->when($selected, fn ($q) => $q->orWhereIn('id', $selected)))
                                    ->orderBy('year')
                                    ->orderByRaw('COALESCE(month, 13)')
                                    ->get()
                                    ->mapWithKeys(function ($inv) {
                                        $due = max(0, (float) $inv->net_amount - $inv->getTotalPaidAttribute());
                                        $period = $inv->month
                                            ? Carbon::createFromFormat('!m', $inv->month)->format('F').' '.$inv->year
                                            : (string) $inv->year;

                                        return [
                                            $inv->id => $inv->feeType->name.' | '.$period.' | Due: ৳'.number_format($due, 2),
                                        ];
                                    });
                            })
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?array $state) {
                                if (empty($state)) {
                                    $set('amount_paid', null);

                                    return;
                                }

                                $total = StudentFeeInvoice::with('payments')
                                    ->whereIn('id', $state)
                                    ->get()
                                    ->sum(fn ($inv) => max(0, (float) $inv->net_amount - $inv->getTotalPaidAttribute()));

                                $set('amount_paid', number_format($total, 2, '.', ''));
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Payment Info')
                    ->columns(2)
                    ->schema([
                        TextInput::make('amount_paid')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('৳'),
                        Select::make('payment_method')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->native(false),
                        TextInput::make('transaction_id')
                            ->label('Transaction / Cheque No.')
                            ->nullable()
                            ->placeholder('For bKash/Nagad/Cheque'),
                        Select::make('received_by')
                            ->label('Received By')
                            ->relationship('receivedBy', 'name')
                            ->searchable()
                            ->nullable()
                            ->native(false),
                        Textarea::make('remarks')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
