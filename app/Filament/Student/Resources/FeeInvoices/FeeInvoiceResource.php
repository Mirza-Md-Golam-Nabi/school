<?php

namespace App\Filament\Student\Resources\FeeInvoices;

use App\Filament\Student\Resources\FeeInvoices\Pages\ListFeeInvoices;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FeeInvoiceResource extends Resource
{
    protected static ?string $model = StudentFeeInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Fee Invoices';

    protected static ?string $modelLabel = 'Fee Invoice';

    protected static ?string $pluralModelLabel = 'Fee Invoices';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                $student = static::getStudentProfile();

                if (! $student) {
                    $query->whereRaw('0=1');

                    return;
                }

                $query->where('student_id', $student->id)
                    ->orderByRaw("(status = 'paid') asc")
                    ->orderByDesc('year')
                    ->orderByDesc('month');
            })
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('feeType.name')
                    ->label('Fee Type')
                    ->searchable()
                    ->description(fn (StudentFeeInvoice $record): string => $record->month
                        ? Carbon::create()->month($record->month)->format('M').' '.$record->year
                        : (string) $record->year)
                    ->wrap(),

                TextColumn::make('original_amount')
                    ->label('Original')
                    ->money('BDT')
                    ->alignEnd()
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('net_amount')
                    ->label('Net Payable')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold')
                    ->description(fn (StudentFeeInvoice $record): ?string => $record->discount_amount > 0
                        ? '৳'.number_format((float) $record->discount_amount, 2).' discount'
                        : null)
                    ->extraAttributes(['class' => 'whitespace-nowrap']),

                TextColumn::make('status')
                    ->badge(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (StudentFeeInvoice $record) => ($record->feeType?->name ?? 'Invoice').' — Details')
                    ->schema([
                        Section::make('Invoice Info')
                            ->schema([
                                TextEntry::make('feeType.name')->label('Fee Type'),
                                TextEntry::make('month')
                                    ->label('Period')
                                    ->formatStateUsing(fn (?int $state, StudentFeeInvoice $record): string => $state
                                        ? Carbon::create()->month($state)->format('M').' '.$record->year
                                        : (string) $record->year),
                                TextEntry::make('original_amount')->label('Original Amount')->money('BDT'),
                                TextEntry::make('discount_amount')->label('Discount')->money('BDT'),
                                TextEntry::make('fine_amount')->label('Fine')->money('BDT'),
                                TextEntry::make('waiver_amount')->label('Waiver')->money('BDT'),
                                TextEntry::make('net_amount')->label('Net Payable')->money('BDT')->weight('bold'),
                                TextEntry::make('status')->badge(),
                            ])
                            ->columns(['default' => 2, 'sm' => 3, 'lg' => 4]),

                        Section::make('Payment History')
                            ->schema([
                                ViewEntry::make('payments')
                                    ->hiddenLabel()
                                    ->view('filament.student.infolists.payment-history'),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeInvoices::route('/'),
        ];
    }

    public static function getStudentProfile(): ?StudentProfile
    {
        /** @var User $user */
        $user = auth()->user();

        return StudentProfile::where('user_id', $user->id)->first();
    }
}
