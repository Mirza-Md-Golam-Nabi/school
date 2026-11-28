<?php

namespace App\Filament\Resources\Exams\Actions;

use App\Actions\ExamNotifications\BuildExamNotificationPreviewAction;
use App\Actions\ExamNotifications\SendExamNotificationAction;
use App\Enums\ExamNotificationChannel;
use App\Enums\ExamNotificationKind;
use App\Enums\Permissions\AcademicPermission;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\User;
use App\Support\Sms\SmsBatchResult;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * "Send Marks" (one subject, or several chosen together) and "Send Result"
 * from inside an exam. Each opens a modal with the exam's channels ticked,
 * a preview (recipients, SMS cost against the balance, missing marks, a
 * sample message, when it was last sent) and sends on confirm.
 */
class ExamNotificationActions
{
    public static function canSend(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (Auth::user()?->can(AcademicPermission::SEND_EXAM_NOTIFICATIONS->value) ?? false);
    }

    /**
     * Row action on a subject of the exam.
     */
    public static function sendSubjectMarks(Exam $exam): Action
    {
        return Action::make('sendSubjectMarks')
            ->label('Send Marks')
            ->tooltip('এই বিষয়ের মার্ক ছাত্র/অভিভাবককে পাঠান')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->iconButton()
            ->visible(fn (): bool => self::canSend())
            ->authorize(fn (): bool => self::canSend())
            ->modalHeading(fn (ExamSubjectConfig $record): string => 'মার্ক পাঠান: '.$record->subjectDisplayName())
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('পাঠান')
            ->schema(fn (ExamSubjectConfig $record): array => [
                self::channelsField($exam, ExamNotificationKind::SubjectMarks),
                self::previewEntry($exam, ExamNotificationKind::SubjectMarks, fn (): array => [(int) $record->subject_id]),
            ])
            ->action(fn (ExamSubjectConfig $record, array $data) => self::send($exam, ExamNotificationKind::SubjectMarks, [(int) $record->subject_id], $data));
    }

    /**
     * Header action: several subjects' marks in one message per student.
     */
    public static function sendSeveralSubjectMarks(Exam $exam): Action
    {
        return Action::make('sendSeveralSubjectMarks')
            ->label('Send Marks')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->visible(fn (): bool => self::canSend())
            ->authorize(fn (): bool => self::canSend())
            ->modalHeading('কয়েকটা বিষয়ের মার্ক একসাথে পাঠান')
            ->modalDescription('প্রতি ছাত্রের বাছাই করা সব বিষয়ের মার্ক একটা মেসেজে যাবে।')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('পাঠান')
            ->schema([
                CheckboxList::make('subject_ids')
                    ->label('বিষয়')
                    ->options(fn (): array => $exam->subjectConfigs()
                        ->with('subject')
                        ->get()
                        ->sortBy(fn (ExamSubjectConfig $config): array => [(int) $config->subject?->sort_order, (int) $config->subject_id])
                        ->mapWithKeys(fn (ExamSubjectConfig $config): array => [$config->subject_id => $config->subjectDisplayName()])
                        ->all())
                    ->columns(['default' => 1, 'sm' => 2])
                    ->bulkToggleable()
                    ->required()
                    ->live(),
                self::channelsField($exam, ExamNotificationKind::SubjectMarks),
                self::previewEntry($exam, ExamNotificationKind::SubjectMarks, fn (Get $get): array => array_map('intval', (array) $get('subject_ids'))),
            ])
            ->action(fn (array $data) => self::send($exam, ExamNotificationKind::SubjectMarks, array_map('intval', (array) ($data['subject_ids'] ?? [])), $data));
    }

    /**
     * Header action on the exam page: the complete result.
     */
    public static function sendResult(): Action
    {
        return Action::make('sendResult')
            ->label('Send Result')
            ->icon(Heroicon::OutlinedMegaphone)
            ->color('warning')
            ->visible(fn (): bool => self::canSend())
            ->authorize(fn (): bool => self::canSend())
            ->modalHeading('সম্পূর্ণ রেজাল্ট পাঠান')
            ->modalDescription('প্রতি ছাত্রের GPA, গ্রেড, মোট নম্বর ও মেধাক্রম যাবে — আগে "Calculate Rankings" চালাতে হবে।')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('পাঠান')
            ->schema(fn (Exam $record): array => [
                self::channelsField($record, ExamNotificationKind::Result),
                self::previewEntry($record, ExamNotificationKind::Result, fn (): array => []),
            ])
            ->action(fn (Exam $record, array $data) => self::send($record, ExamNotificationKind::Result, [], $data));
    }

    private static function channelsField(Exam $exam, ExamNotificationKind $kind): CheckboxList
    {
        return CheckboxList::make('channels')
            ->label('কোন মাধ্যমে পাঠাবেন')
            ->options(collect(ExamNotificationChannel::cases())->mapWithKeys(fn (ExamNotificationChannel $channel): array => [$channel->value => $channel->getLabel()])->all())
            ->default(array_map(fn (ExamNotificationChannel $channel): string => $channel->value, $exam->channelsFor($kind)))
            ->helperText('ডিফল্ট আসে পরীক্ষার Notification Settings থেকে।')
            ->required()
            ->live();
    }

    /**
     * @param  \Closure(Get): list<int>  $subjectIds
     */
    private static function previewEntry(Exam $exam, ExamNotificationKind $kind, \Closure $subjectIds): TextEntry
    {
        return TextEntry::make('preview')
            ->label('Preview')
            ->state(function (Get $get) use ($exam, $kind, $subjectIds): HtmlString {
                $ids = $subjectIds($get);

                if ($kind === ExamNotificationKind::SubjectMarks && $ids === []) {
                    return new HtmlString('<span class="text-xs sm:text-sm text-gray-500">বিষয় বাছাই করুন।</span>');
                }

                $channels = ExamNotificationChannel::listFrom((array) $get('channels'));
                $preview = app(BuildExamNotificationPreviewAction::class)->handle($exam, $kind, $ids, $channels);

                return self::previewHtml($preview, $kind, $channels);
            })
            ->html();
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  list<ExamNotificationChannel>  $channels
     */
    private static function previewHtml(array $preview, ExamNotificationKind $kind, array $channels): HtmlString
    {
        $lines = [];

        if ($preview['recipients'] === 0) {
            $lines[] = self::line('danger', $kind === ExamNotificationKind::Result
                ? 'কোনো ছাত্রের ranking নেই — আগে "Calculate Rankings" চালান।'
                : 'বাছাই করা বিষয়ে কোনো ছাত্রের মার্ক দেওয়া হয়নি।');

            return new HtmlString(self::wrap($lines));
        }

        $lines[] = self::line(null, "<strong>{$preview['recipients']}</strong> জন ছাত্র পাবে।");

        if (in_array(ExamNotificationChannel::Push, $channels, true)) {
            $lines[] = self::line(null, 'Push: ছাত্রের app-এ (যার app install করা আছে)।');
        }

        if ($preview['sms_requested']) {
            if ($preview['sms_blocked_reason']) {
                $lines[] = self::line('warning', e($preview['sms_blocked_reason']));
            } else {
                $lines[] = self::line(null, sprintf(
                    'SMS: %d টি (%d পার্ট) — খরচ ৳%s, ব্যালেন্স ৳%s',
                    $preview['sms_count'],
                    $preview['sms_parts'],
                    number_format($preview['sms_cost'], 2),
                    number_format($preview['sms_balance'], 2),
                ));

                if ($preview['sms_cost'] > $preview['sms_balance']) {
                    $lines[] = self::line('danger', 'ব্যালেন্স অপর্যাপ্ত — কোনো SMS যাবে না। আগে রিচার্জ করুন।');
                }

                if ($preview['sms_without_phone'] > 0) {
                    $lines[] = self::line('warning', "{$preview['sms_without_phone']} জনের সঠিক মোবাইল নম্বর নেই — তাদের SMS যাবে না।");
                }
            }
        }

        if ($preview['students_without_marks'] > 0) {
            $lines[] = self::line('warning', "{$preview['students_without_marks']} জন ছাত্রের বাছাই করা কোনো বিষয়ের মার্ক এখনো দেওয়া হয়নি — সেই বিষয় তাদের মেসেজে থাকবে না।");
        }

        if ($preview['last_sent_at']) {
            $lines[] = self::line('warning', "আগে পাঠানো হয়েছে: {$preview['last_sent_at']} — আবার পাঠালে সবাই আবার পাবে।");
        }

        $sample = $preview['sms_requested'] && ! $preview['sms_blocked_reason'] ? $preview['sample_sms'] : $preview['sample_push'];
        $lines[] = '<div class="mt-2 rounded-lg bg-gray-50 p-2 text-xs sm:text-sm dark:bg-white/5"><span class="font-medium">নমুনা:</span> '.e((string) $sample).'</div>';

        return new HtmlString(self::wrap($lines));
    }

    private static function line(?string $tone, string $html): string
    {
        $color = match ($tone) {
            'danger' => 'text-danger-600 dark:text-danger-400',
            'warning' => 'text-warning-600 dark:text-warning-400',
            default => '',
        };

        return "<div class=\"{$color}\">{$html}</div>";
    }

    /**
     * @param  list<string>  $lines
     */
    private static function wrap(array $lines): string
    {
        return '<div class="space-y-1 text-xs sm:text-sm">'.implode('', $lines).'</div>';
    }

    /**
     * @param  list<int>  $subjectIds
     * @param  array<string, mixed>  $data
     */
    private static function send(Exam $exam, ExamNotificationKind $kind, array $subjectIds, array $data): void
    {
        $channels = ExamNotificationChannel::listFrom((array) ($data['channels'] ?? []));
        /** @var User|null $user */
        $user = Auth::user();

        ['batch' => $batch, 'sms' => $sms] = app(SendExamNotificationAction::class)->handle($exam, $kind, $subjectIds, $channels, $user);

        if ($batch === null) {
            Notification::make()
                ->danger()
                ->title('কাউকে পাঠানো যায়নি')
                ->body($kind === ExamNotificationKind::Result ? 'কোনো ছাত্রের ranking নেই — আগে "Calculate Rankings" চালান।' : 'বাছাই করা বিষয়ে কোনো মার্ক দেওয়া হয়নি।')
                ->send();

            return;
        }

        $parts = [];

        if (in_array(ExamNotificationChannel::Push, $channels, true)) {
            $parts[] = "Push: {$batch->push_count} জন";
        }

        if ($sms?->isQueued()) {
            $parts[] = "SMS: {$sms->queued} টি (৳".number_format($sms->cost, 2).')';
        }

        $notification = Notification::make()
            ->title("{$kind->getLabel()} পাঠানো হয়েছে")
            ->body(implode(' · ', $parts) ?: null);

        if ($sms !== null && ! $sms->isQueued()) {
            $notification->warning()->body(implode(' · ', [...$parts, 'SMS যায়নি: '.self::smsFailureReason($sms)]));
        } else {
            $notification->success();
        }

        $notification->send();
    }

    private static function smsFailureReason(SmsBatchResult $sms): string
    {
        return match ($sms->status) {
            SmsBatchResult::DISABLED => 'SMS Settings-এ SMS বা এই event বন্ধ আছে।',
            SmsBatchResult::NOTHING_TO_SEND => 'কারো সঠিক মোবাইল নম্বর নেই।',
            default => $sms->rejectionMessage(),
        };
    }
}
