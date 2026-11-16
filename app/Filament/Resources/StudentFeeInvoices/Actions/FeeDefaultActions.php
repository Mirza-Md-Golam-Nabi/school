<?php

namespace App\Filament\Resources\StudentFeeInvoices\Actions;

use App\Actions\MarkFeeInvoicesAsDefaultAction;
use App\Actions\RecoverDefaultedFeeInvoicesAction;
use App\Enums\InvoiceStatus;
use App\Enums\Permissions\FeePermission;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Fee invoice "Default" (অনাদায়ী ঘোষণা) ও "Recover"-এর Filament action-গুলো —
 * invoice table, class-wise student table ও student profile সব জায়গায় একই আচরণ।
 * Permission ছাড়া (super-admin বাদে) কেউ এগুলো দেখতেও পায় না।
 */
class FeeDefaultActions
{
    public static function canMarkDefault(): bool
    {
        return Auth::user()?->can(FeePermission::MARK_FEE_DEFAULT->value) ?? false;
    }

    public static function canRecoverDefault(): bool
    {
        return Auth::user()?->can(FeePermission::RECOVER_FEE_DEFAULT->value) ?? false;
    }

    /**
     * একটি invoice-এর জন্য row action।
     */
    public static function markInvoice(): Action
    {
        return Action::make('markAsDefault')
            ->label('Mark as Default')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (StudentFeeInvoice $record): bool => self::canMarkDefault()
                && in_array($record->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true))
            ->authorize(fn (): bool => self::canMarkDefault())
            ->requiresConfirmation()
            ->modalHeading('Invoice Default করবেন?')
            ->modalDescription(fn (StudentFeeInvoice $record): string => 'বাকি ৳'.number_format($record->outstanding_amount, 2)
                .' অনাদায়ী (default) হিসেবে বকেয়া থেকে আলাদা হবে। Account-এ কোনো এন্ট্রি হবে না। পরে প্রয়োজনে Recover করা যাবে।')
            ->schema([self::reasonField()])
            ->action(function (StudentFeeInvoice $record, array $data): void {
                $count = app(MarkFeeInvoicesAsDefaultAction::class)->handle([$record], (int) Auth::id(), $data['default_reason']);

                self::notifyMarked($count);
            });
    }

    /**
     * একসাথে কয়েকটা invoice default করার bulk action।
     */
    public static function markInvoicesInBulk(): BulkAction
    {
        return BulkAction::make('markAsDefault')
            ->label('Mark as Default')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (): bool => self::canMarkDefault())
            ->authorize(fn (): bool => self::canMarkDefault())
            ->requiresConfirmation()
            ->modalHeading('নির্বাচিত invoice-গুলো Default করবেন?')
            ->modalDescription('শুধু Unpaid/Partial invoice-এর বাকি অংশ default হবে; Paid/Waived invoice বাদ যাবে। Account-এ কোনো এন্ট্রি হবে না।')
            ->schema([self::reasonField()])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $count = app(MarkFeeInvoicesAsDefaultAction::class)->handle($records, (int) Auth::id(), $data['default_reason']);

                self::notifyMarked($count);
            });
    }

    public static function recoverInvoice(): Action
    {
        return Action::make('recoverDefault')
            ->label('Recover')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (StudentFeeInvoice $record): bool => self::canRecoverDefault() && $record->isDefaulted())
            ->authorize(fn (): bool => self::canRecoverDefault())
            ->requiresConfirmation()
            ->modalHeading('Default Recover করবেন?')
            ->modalDescription(fn (StudentFeeInvoice $record): string => '৳'.number_format((float) $record->default_amount, 2)
                .' আবার আদায়যোগ্য বকেয়া হিসেবে দেখাবে এবং payment নেওয়া যাবে।')
            ->action(function (StudentFeeInvoice $record): void {
                app(RecoverDefaultedFeeInvoicesAction::class)->handle([$record]);

                Notification::make()->success()->title('Invoice আবার আদায়যোগ্য করা হয়েছে')->send();
            });
    }

    /**
     * একজন student-এর সব বকেয়া একসাথে default — StudentProfile record-এর উপর।
     */
    public static function markStudentDues(): Action
    {
        return Action::make('markStudentDuesAsDefault')
            ->label('Mark All Dues as Default')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (): bool => self::canMarkDefault())
            ->authorize(fn (): bool => self::canMarkDefault())
            ->requiresConfirmation()
            ->modalHeading(fn (StudentProfile $record): string => ($record->user?->name ?? 'Student').' — সব বকেয়া Default করবেন?')
            ->modalDescription(fn (StudentProfile $record): string => 'মোট ৳'.number_format(self::studentOutstanding($record), 2)
                .' বকেয়া অনাদায়ী (default) হিসেবে আলাদা হবে। Account-এ কোনো এন্ট্রি হবে না। পরে প্রয়োজনে Recover করা যাবে।')
            ->schema([self::reasonField()])
            ->action(function (StudentProfile $record, array $data): void {
                $count = app(MarkFeeInvoicesAsDefaultAction::class)->forStudent($record, (int) Auth::id(), $data['default_reason']);

                self::notifyMarked($count);
            });
    }

    /**
     * একজন student-এর সব default বকেয়া আবার আদায়যোগ্য করা (আবার ভর্তির সময়)।
     */
    public static function recoverStudentDues(): Action
    {
        return Action::make('recoverStudentDefaults')
            ->label(fn (StudentProfile $record): string => 'Default বকেয়া: ৳'.number_format($record->defaultedDueAmount(), 0))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->visible(fn (StudentProfile $record): bool => $record->defaultedDueAmount() > 0)
            ->modalHeading('পুরনো Default বকেয়া')
            ->modalDescription(fn (StudentProfile $record): string => self::defaultedDuesWarning($record)
                .(self::canRecoverDefault()
                    ? ' "Recover" করলে বকেয়া আবার আদায়যোগ্য হবে; না চাইলে Close করুন — default হিসেবেই থাকবে।'
                    : ''))
            ->schema([
                RepeatableEntry::make('defaultedInvoices')
                    ->hiddenLabel()
                    ->state(fn (StudentProfile $record) => $record->feeInvoices()->defaulted()->with(['feeType', 'defaultedBy'])->get())
                    ->schema([
                        TextEntry::make('feeType.name')->label('Fee Type'),
                        TextEntry::make('period')
                            ->label('Period')
                            ->state(fn (StudentFeeInvoice $record): string => $record->month
                                ? Carbon::create()->month($record->month)->format('M').' '.$record->year
                                : (string) $record->year),
                        TextEntry::make('default_amount')->label('Defaulted')->money('BDT')->color('danger'),
                        TextEntry::make('defaulted_at')->label('Date')->date(),
                        TextEntry::make('default_reason')->label('Reason')->columnSpanFull(),
                    ])
                    ->columns(['default' => 2, 'sm' => 4]),
            ])
            ->modalSubmitActionLabel('Recover')
            ->modalSubmitAction(fn (Action $action): Action => $action->visible(self::canRecoverDefault()))
            ->modalCancelActionLabel('Close')
            ->action(function (StudentProfile $record): void {
                abort_unless(self::canRecoverDefault(), 403);

                $count = app(RecoverDefaultedFeeInvoicesAction::class)->forStudent($record);

                Notification::make()
                    ->success()
                    ->title("{$count}টি invoice আবার আদায়যোগ্য করা হয়েছে")
                    ->send();
            });
    }

    /**
     * Promotion/ভর্তি/marksheet-এর confirmation-এ দেখানোর বার্তা — না আটকিয়ে শুধু জানায়।
     */
    public static function defaultedDuesWarning(StudentProfile $student): ?string
    {
        $amount = $student->defaultedDueAmount();

        if ($amount <= 0) {
            return null;
        }

        return '⚠ এই student-এর ৳'.number_format($amount, 2).' default (অনাদায়ী) বকেয়া আছে।';
    }

    private static function reasonField(): Textarea
    {
        return Textarea::make('default_reason')
            ->label('কারণ')
            ->placeholder('যেমন: স্কুল ছেড়ে চলে গেছে, যোগাযোগ করা যাচ্ছে না')
            ->required()
            ->maxLength(1000)
            ->rows(2);
    }

    private static function studentOutstanding(StudentProfile $student): float
    {
        return (float) $student->feeInvoices()->payable()->with('payments')->get()
            ->sum(fn (StudentFeeInvoice $invoice): float => $invoice->outstanding_amount);
    }

    private static function notifyMarked(int $count): void
    {
        if ($count === 0) {
            Notification::make()->warning()->title('Default করার মতো কোনো বকেয়া invoice পাওয়া যায়নি')->send();

            return;
        }

        Notification::make()->success()->title("{$count}টি invoice default করা হয়েছে")->send();
    }
}
