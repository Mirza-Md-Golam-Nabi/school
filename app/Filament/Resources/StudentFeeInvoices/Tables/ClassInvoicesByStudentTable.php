<?php

namespace App\Filament\Resources\StudentFeeInvoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource as AdminStudentFeeInvoiceResource;
use App\Filament\Teacher\Resources\StudentFeeInvoices\StudentFeeInvoiceResource as TeacherStudentFeeInvoiceResource;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassInvoicesByStudentTable
{
    /**
     * One row per student (grouping that student's invoices), rather than one
     * row per invoice — a student with several invoices otherwise repeats
     * their name across several rows in the plain invoice table.
     */
    public static function configure(Table $table, int $classId): Table
    {
        return $table
            ->query(fn () => StudentProfile::query()
                ->where('current_class_id', $classId)
                ->whereHas('feeInvoices', fn ($query) => $query->payable())
                ->with(['user', 'feeInvoices.feeType', 'feeInvoices.payments']))
            ->defaultSort('roll_no')
            ->recordUrl(null)
            // Without an explicit recordAction, clicking anywhere on a row (not just
            // the icon button) falls back to a default "view" action name, which
            // isn't defined on this table — Filament then resolves it against the
            // owning resource's default table (StudentFeeInvoicesTable) instead,
            // a *different*, StudentFeeInvoice-typed action. Since this table's
            // records are StudentProfile, that mismatch throws a TypeError. Pin
            // row clicks to this table's own action to avoid the fallback.
            ->recordAction('viewPending')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('roll_no')
                    ->label('Roll')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('invoice_count')
                    ->label('Pending Invoices')
                    ->state(fn (StudentProfile $record) => self::pendingInvoices($record)->count())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('total_due')
                    ->label('Total Due')
                    ->state(fn (StudentProfile $record) => self::totalDue($record))
                    ->money('BDT')
                    ->weight('bold')
                    ->color(fn (float $state) => $state > 0 ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('viewPending')
                    ->label('View')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedEye)
                    ->modalWidth('3xl')
                    ->modalHeading(fn (StudentProfile $record) => ($record->user?->name ?? 'Student').' — Pending Invoices')
                    ->schema([
                        Section::make()
                            ->schema([
                                RepeatableEntry::make('pendingInvoices')
                                    ->hiddenLabel()
                                    ->state(fn (StudentProfile $record) => self::pendingInvoices($record))
                                    ->schema([
                                        TextEntry::make('feeType.name')->label('Fee Type'),
                                        TextEntry::make('period')
                                            ->label('Period')
                                            ->state(fn ($record) => $record->month
                                                ? Carbon::create()->month($record->month)->format('M').' '.$record->year
                                                : (string) $record->year),
                                        TextEntry::make('net_amount')->label('Net Payable')->money('BDT'),
                                        TextEntry::make('due')
                                            ->label('Due')
                                            ->state(fn ($record) => max(0, (float) $record->net_amount - $record->payments->sum('amount_paid')))
                                            ->money('BDT')
                                            ->color('danger'),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('edit')
                                            ->label('')
                                            ->state('Edit')
                                            ->icon(Heroicon::OutlinedPencilSquare)
                                            ->color('primary')
                                            ->url(fn (StudentFeeInvoice $record): string => self::resourceForCurrentPanel()::getUrl('edit', ['record' => $record]))
                                            ->openUrlInNewTab()
                                            ->visible(fn (StudentFeeInvoice $record): bool => self::resourceForCurrentPanel()::canEdit($record)),
                                    ])
                                    ->columns(['default' => 2, 'sm' => 3, 'lg' => 6]),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    /**
     * @return class-string<AdminStudentFeeInvoiceResource>|class-string<TeacherStudentFeeInvoiceResource>
     */
    private static function resourceForCurrentPanel(): string
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            ? AdminStudentFeeInvoiceResource::class
            : TeacherStudentFeeInvoiceResource::class;
    }

    private static function totalDue(StudentProfile $record): float
    {
        return self::pendingInvoices($record)
            ->sum(fn ($invoice) => max(0, (float) $invoice->net_amount - $invoice->payments->sum('amount_paid')));
    }

    private static function pendingInvoices(StudentProfile $record)
    {
        return $record->feeInvoices
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->values();
    }
}
