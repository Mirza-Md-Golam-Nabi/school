<?php

namespace App\Filament\Teacher\Resources\StudentFeeInvoices;

use App\Enums\InvoiceStatus;
use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\CreateStudentFeeInvoice;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\EditStudentFeeInvoice;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\ListStudentFeeInvoices;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Schemas\StudentFeeInvoiceForm;
use App\Models\StudentFeeInvoice;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StudentFeeInvoiceResource extends Resource
{
    use HasEntityPermissions;
    use ScopesToClassTeacherStudents;

    protected static ?string $model = StudentFeeInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Fee';

    protected static ?string $navigationLabel = 'Fee Invoices';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('student', fn ($query) => $query->whereIn('current_class_id', static::currentTeacherClassIds()));
    }

    public static function form(Schema $schema): Schema
    {
        return StudentFeeInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.roll_no')
                    ->label('Roll')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('feeType.name')
                    ->label('Fee Type')
                    ->searchable(),
                TextColumn::make('month')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? Carbon::create()->month($state)->format('M')
                        : '—')
                    ->alignCenter(),
                TextColumn::make('year')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('net_amount')
                    ->label('Net Payable')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (StudentFeeInvoice $record) => ($record->feeType?->name ?? 'Invoice').' — Details')
                    ->schema([
                        Section::make('Student Info')
                            ->schema([
                                TextEntry::make('student.user.name')->label('Name'),
                                TextEntry::make('student.roll_no')->label('Roll No.'),
                            ])
                            ->columns(['default' => 2, 'sm' => 2]),

                        Section::make('Invoice Info')
                            ->schema([
                                TextEntry::make('feeType.name')->label('Fee Type'),
                                TextEntry::make('month')
                                    ->label('Period')
                                    ->formatStateUsing(fn (?int $state, StudentFeeInvoice $record): string => $state
                                        ? Carbon::create()->month($state)->format('M').' '.$record->year
                                        : (string) $record->year),
                                TextEntry::make('net_amount')->label('Net Payable')->money('BDT')->weight('bold'),
                                TextEntry::make('status')->badge(),
                            ])
                            ->columns(['default' => 2, 'sm' => 3, 'lg' => 4]),

                        Section::make('Payment History')
                            ->schema([
                                RepeatableEntry::make('payments')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('receipt_no')->label('Receipt'),
                                        TextEntry::make('amount_paid')->label('Amount')->money('BDT'),
                                        TextEntry::make('payment_date')->label('Date')->date(),
                                        TextEntry::make('payment_method')->label('Method')->badge(),
                                    ])
                                    ->columns(['default' => 2, 'sm' => 3, 'lg' => 4]),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentFeeInvoices::route('/'),
            'class-invoices' => ManageClassStudentFeeInvoices::route('/class-invoices'),
            'create' => CreateStudentFeeInvoice::route('/create'),
            'edit' => EditStudentFeeInvoice::route('/{record}/edit'),
        ];
    }
}
