<?php

namespace App\Filament\Pages;

use App\Actions\CollectPromotionFeesAction;
use App\Actions\PromoteStudentsAction;
use App\Actions\ResolveDefaultPromotionOptionalSubjects;
use App\Enums\ExamConfigType;
use App\Enums\PaymentMethod;
use App\Enums\PromotionStatus;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Section;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class PromoteStudentsForClass extends Page implements HasTable
{
    use InteractsWithTable;

    private const LEAVING_STATUSES = [PromotionStatus::Graduated->value, PromotionStatus::Transferred->value, PromotionStatus::Dropped->value];

    private const ADVANCING_STATUSES = [PromotionStatus::Promoted->value, PromotionStatus::Repeated->value];

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.promote-students-for-class';

    #[Url(as: 'classId')]
    public int $classId = 0;

    #[Url(as: 'year')]
    public int $year = 0;

    private ?CollectPromotionFeesAction $collectPromotionFees = null;

    public function mount(): void
    {
        abort_unless($this->classId && $this->year, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Promote — '.($this->resolveClass()?->name ?? 'Class');
    }

    public function getBreadcrumbs(): array
    {
        return [
            PromoteStudents::getUrl() => 'Promote',
            '' => $this->resolveClass()?->name ?? 'Class',
        ];
    }

    public function getBackUrl(): string
    {
        return PromoteStudents::getUrl(['year' => $this->year]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->icon('heroicon-o-arrow-left')
                ->url($this->getBackUrl())
                ->color('gray'),

            Action::make('bulkPromote')
                ->label('Bulk Promote')
                ->icon('heroicon-o-queue-list')
                ->color('success')
                ->url(BulkPromoteStudentsForClass::getUrl(['classId' => $this->classId, 'year' => $this->year])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StudentProfile::query()
                    ->with(['user', 'class', 'section'])
                    ->where('current_class_id', $this->classId)
                    ->where('session_year', $this->year)
                    ->active()
            )
            ->defaultSort('roll_no')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roll_no')
                    ->label('Roll No')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->recordActions([
                $this->promoteAction(),
            ]);
    }

    private function promoteAction(): Action
    {
        return Action::make('promote')
            ->label('Promote')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('info')
            ->iconButton()
            ->modalHeading(fn (StudentProfile $record): string => 'Promote — '.($record->user?->name ?? $record->roll_no))
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Promote')
            ->successNotificationTitle('Student has been promoted')
            ->fillForm(function (StudentProfile $record): array {
                $nextClassId = $this->resolveNextClassId($record);
                $status = $nextClassId ? PromotionStatus::Promoted : PromotionStatus::Graduated;
                $groupId = $this->resolveDefaultGroupId($record, $nextClassId);
                $optionalDefaults = $this->resolveDefaultOptionalSubjects($record, $nextClassId, $groupId);

                return [
                    'status' => $status->value,
                    'class_id' => $nextClassId,
                    'section_id' => null,
                    'group_id' => $groupId,
                    'main_optional_subject_id' => $optionalDefaults['main_optional_subject_id'],
                    'extra_optional_subject_id' => $optionalDefaults['extra_optional_subject_id'],
                    'roll_no' => $status === PromotionStatus::Promoted
                        ? ($this->resolveMeritRoll($record) ?? $record->roll_no)
                        : null,
                    'remarks' => null,
                    'fee_structure_ids' => [],
                    'fee_months' => [1],
                    'amount_paid' => null,
                    'payment_method' => PaymentMethod::Cash->value,
                    'receipt_no' => 'RCP-'.strtoupper(uniqid()),
                    'payment_date' => now()->toDateString(),
                    'transaction_id' => null,
                ];
            })
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(PromotionStatus::class)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, StudentProfile $record): void {
                        $classId = match ($this->statusValue($get)) {
                            PromotionStatus::Repeated->value => $record->current_class_id,
                            PromotionStatus::Promoted->value => $this->resolveNextClassId($record),
                            default => null,
                        };

                        if ((int) $get('class_id') === (int) $classId) {
                            return;
                        }

                        $set('class_id', $classId);
                        $this->resetTargetClassDependents($set, $record, $classId);
                    }),

                Select::make('class_id')
                    ->label('Target Class')
                    ->placeholder('—')
                    ->options(fn () => Classes::active()->orderBy('order')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->required(fn (Get $get): bool => in_array($this->statusValue($get), self::ADVANCING_STATUSES, true))
                    ->disabled(fn (Get $get): bool => in_array($this->statusValue($get), self::LEAVING_STATUSES, true))
                    ->afterStateUpdated(fn (Set $set, Get $get, StudentProfile $record) => $this->resetTargetClassDependents(
                        $set,
                        $record,
                        $get('class_id') ? (int) $get('class_id') : null,
                    )),

                Select::make('section_id')
                    ->label('Section')
                    ->options(fn (Get $get): array => $get('class_id')
                        ? Section::dropdownOptionsByClass((int) $get('class_id'))->toArray()
                        : [])
                    ->searchable()
                    ->visible(fn (Get $get): bool => ! in_array($this->statusValue($get), self::LEAVING_STATUSES, true)
                        && $get('class_id')
                        && Section::dropdownOptionsByClass((int) $get('class_id'))->isNotEmpty()),

                Select::make('group_id')
                    ->label('Group')
                    ->options(function (Get $get): array {
                        $classId = $get('class_id');

                        if (! $classId) {
                            return [];
                        }

                        $class = Classes::find($classId);

                        if (! $class?->has_group) {
                            return [];
                        }

                        return $class->groups()->where('groups.is_active', true)->pluck('groups.name', 'groups.id')->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->visible(fn (Get $get): bool => ! in_array($this->statusValue($get), self::LEAVING_STATUSES, true)
                        && $get('class_id')
                        && Classes::find($get('class_id'))?->has_group)
                    ->afterStateUpdated(function (Set $set, Get $get, StudentProfile $record): void {
                        $classId = $get('class_id') ? (int) $get('class_id') : null;
                        $groupId = $get('group_id') ? (int) $get('group_id') : null;
                        $optionalDefaults = $this->resolveDefaultOptionalSubjects($record, $classId, $groupId);

                        $set('main_optional_subject_id', $optionalDefaults['main_optional_subject_id']);
                        $set('extra_optional_subject_id', $optionalDefaults['extra_optional_subject_id']);
                    }),

                Select::make('main_optional_subject_id')
                    ->label('Main Optional Subject')
                    ->helperText('সাধারণত আগের ক্লাসের subject-ই বহাল থাকে — group বা subject বদলে গেলে নতুন করে বেছে নিন। শুধু নির্বাচিত group-এর নিজস্ব optional subject')
                    ->options(fn (Get $get) => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id'), includeAllGroups: false))
                    ->visible(fn (Get $get): bool => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id'), includeAllGroups: false)->isNotEmpty())
                    ->searchable()
                    ->live()
                    ->placeholder('Select main optional subject'),

                Select::make('extra_optional_subject_id')
                    ->label('Extra Optional Subject')
                    ->helperText('সাধারণত আগের ক্লাসের subject-ই বহাল থাকে — group বা subject বদলে গেলে নতুন করে বেছে নিন। নির্বাচিত group-এর optional subject + All Groups optional subject')
                    ->options(fn (Get $get) => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id')))
                    ->visible(fn (Get $get): bool => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id'))->isNotEmpty())
                    ->searchable()
                    ->placeholder('Select extra optional subject')
                    ->rules(fn (Get $get): array => [Rule::notIn(array_filter([$get('main_optional_subject_id')]))])
                    ->validationMessages([
                        'not_in' => 'Extra optional subject must be different from the main optional subject.',
                    ]),

                TextInput::make('roll_no')
                    ->label('New Roll (Merit)')
                    ->numeric()
                    ->minValue(1)
                    ->required(fn (Get $get): bool => in_array($this->statusValue($get), self::ADVANCING_STATUSES, true))
                    ->disabled(fn (Get $get): bool => in_array($this->statusValue($get), self::LEAVING_STATUSES, true)),

                TextEntry::make('final_merit_rank')
                    ->label('Final Merit Rank (Main Exam)')
                    ->state(fn (StudentProfile $record): string => (string) ($this->resolveMeritRoll($record) ?? '—'))
                    ->visible(fn (Get $get, StudentProfile $record): bool => in_array($this->statusValue($get), self::LEAVING_STATUSES, true)
                        && $this->resolveMeritRoll($record) !== null),

                Textarea::make('remarks')
                    ->label('Remarks')
                    ->rows(2),

                $this->feeCollectionSection(),
            ])
            ->action(function (StudentProfile $record, array $data, Action $action): void {
                $status = $data['status'] instanceof PromotionStatus
                    ? $data['status']
                    : PromotionStatus::from($data['status']);

                if (
                    in_array($status, [PromotionStatus::Promoted, PromotionStatus::Repeated], true)
                    && (blank($data['class_id'] ?? null) || blank($data['roll_no'] ?? null))
                ) {
                    Notification::make()
                        ->danger()
                        ->title('Promoted/Repeated-এর জন্য Target Class ও New Roll পূরণ করা আবশ্যক')
                        ->send();

                    $action->halt();
                }

                $collectsFees = in_array($status, [PromotionStatus::Promoted, PromotionStatus::Repeated], true);
                $feeStructureIds = $collectsFees ? array_filter((array) ($data['fee_structure_ids'] ?? [])) : [];
                $feeSessionYear = $this->feeSessionYear($record);

                $payment = DB::transaction(function () use ($record, $data, $feeStructureIds, $feeSessionYear): ?FeePayment {
                    app(PromoteStudentsAction::class)->handle([
                        $record->id => [
                            'status' => $data['status'],
                            'class_id' => $data['class_id'] ?? null,
                            'section_id' => $data['section_id'] ?? null,
                            'group_id' => $data['group_id'] ?? null,
                            'roll_no' => $data['roll_no'] ?? null,
                            'remarks' => $data['remarks'] ?? null,
                            'main_optional_subject_id' => $data['main_optional_subject_id'] ?? null,
                            'extra_optional_subject_id' => $data['extra_optional_subject_id'] ?? null,
                        ],
                    ], Auth::id());

                    if ($feeStructureIds === []) {
                        return null;
                    }

                    return app(CollectPromotionFeesAction::class)->handle(
                        $record,
                        (int) $data['class_id'],
                        $feeSessionYear,
                        $feeStructureIds,
                        (array) ($data['fee_months'] ?? []),
                        [
                            'amount_paid' => $data['amount_paid'] ?? null,
                            'receipt_no' => $data['receipt_no'] ?? null,
                            'payment_method' => $data['payment_method'] ?? null,
                            'payment_date' => $data['payment_date'] ?? null,
                            'transaction_id' => $data['transaction_id'] ?? null,
                            'received_by' => Auth::id(),
                        ],
                    );
                });

                if ($payment) {
                    Notification::make()
                        ->success()
                        ->title('Fee payment recorded')
                        ->actions([
                            Action::make('downloadSlip')
                                ->label('Download Payment Slip')
                                ->url(route('fee-payments.slip.download', $payment->payment_batch_id))
                                ->openUrlInNewTab()
                                ->button(),
                        ])
                        ->send();
                }
            });
    }

    /**
     * ঐচ্ছিক ফি কালেকশন — Target Class ও নতুন সেশনের fee structure থেকে admin যেগুলো
     * বেছে নেয় সেগুলোর ইনভয়েস তৈরি হয়; টাকা কম দিলে (বা না দিলে) বাকিটা due থাকে।
     */
    private function feeCollectionSection(): SchemaSection
    {
        return SchemaSection::make('Fee Collection')
            ->description('ঐচ্ছিক — কোনো ফি সিলেক্ট না করলে শুধু promote হবে।')
            ->compact()
            ->visible(fn (Get $get): bool => in_array($this->statusValue($get), self::ADVANCING_STATUSES, true) && filled($get('class_id')))
            ->schema([
                TextEntry::make('fee_structure_warning')
                    ->hiddenLabel()
                    ->color('warning')
                    ->state(fn (Get $get, StudentProfile $record): string => (Classes::find($get('class_id'))?->name ?? 'এই ক্লাস')
                        .' — '.$this->feeSessionYear($record).' সেশনের কোনো active fee structure নেই। আগে Fee Structure তৈরি করুন, অথবা ফি ছাড়াই promote করুন।')
                    ->visible(fn (Get $get, StudentProfile $record): bool => $this->feeStructureOptions($get, $record) === []),

                Select::make('fee_structure_ids')
                    ->label('Fees to Collect')
                    ->multiple()
                    ->options(fn (Get $get, StudentProfile $record): array => $this->feeStructureOptions($get, $record))
                    ->visible(fn (Get $get, StudentProfile $record): bool => $this->feeStructureOptions($get, $record) !== [])
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get, StudentProfile $record) => $this->syncFeeAmount($set, $get, $record)),

                Select::make('fee_months')
                    ->label('Months (Monthly Fees)')
                    ->multiple()
                    ->options(fn (): array => collect(range(1, 12))
                        ->mapWithKeys(fn (int $month): array => [$month => Carbon::createFromFormat('!m', (string) $month)->format('F')])
                        ->all())
                    ->visible(fn (Get $get): bool => $this->hasMonthlyFeeSelected($get))
                    ->required(fn (Get $get): bool => $this->hasMonthlyFeeSelected($get))
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get, StudentProfile $record) => $this->syncFeeAmount($set, $get, $record)),

                TextEntry::make('fee_total_payable')
                    ->label('Total Payable')
                    ->helperText('Discount এবং আগে পরিশোধ করা টাকা বাদ দিয়ে')
                    ->state(fn (Get $get, StudentProfile $record): string => '৳'.number_format($this->feePayableTotal($get, $record), 2))
                    ->visible(fn (Get $get): bool => $this->hasFeeSelected($get)),

                TextInput::make('amount_paid')
                    ->label('Amount Received')
                    ->helperText('কম দিলে বাকিটা due থাকবে; ০ বা খালি রাখলে শুধু ইনভয়েস তৈরি হবে।')
                    ->numeric()
                    ->prefix('৳')
                    ->minValue(0)
                    ->maxValue(fn (Get $get, StudentProfile $record): float => $this->feePayableTotal($get, $record))
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => $this->hasFeeSelected($get)),

                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options(PaymentMethod::class)
                    ->required(fn (Get $get): bool => $this->isReceivingPayment($get))
                    ->visible(fn (Get $get): bool => $this->isReceivingPayment($get)),

                TextInput::make('receipt_no')
                    ->label('Receipt No.')
                    ->required(fn (Get $get): bool => $this->isReceivingPayment($get))
                    ->rules([Rule::unique(FeePayment::class, 'receipt_no')])
                    ->visible(fn (Get $get): bool => $this->isReceivingPayment($get)),

                DatePicker::make('payment_date')
                    ->label('Payment Date')
                    ->native(false)
                    ->required(fn (Get $get): bool => $this->isReceivingPayment($get))
                    ->visible(fn (Get $get): bool => $this->isReceivingPayment($get)),

                TextInput::make('transaction_id')
                    ->label('Transaction / Cheque No.')
                    ->placeholder('For bKash/Nagad/Cheque')
                    ->visible(fn (Get $get): bool => $this->isReceivingPayment($get)),
            ]);
    }

    /**
     * Target Class বদলালে (সরাসরি বা status বদলের কারণে) তার ওপর নির্ভরশীল সব field
     * নতুন ক্লাস অনুযায়ী রিসেট হয় — ফি সিলেকশনও, কারণ fee structure ক্লাসভিত্তিক।
     */
    private function resetTargetClassDependents(Set $set, StudentProfile $record, ?int $classId): void
    {
        $groupId = $this->resolveDefaultGroupId($record, $classId);
        $optionalDefaults = $this->resolveDefaultOptionalSubjects($record, $classId, $groupId);

        $set('section_id', null);
        $set('group_id', $groupId);
        $set('main_optional_subject_id', $optionalDefaults['main_optional_subject_id']);
        $set('extra_optional_subject_id', $optionalDefaults['extra_optional_subject_id']);
        $set('fee_structure_ids', []);
        $set('amount_paid', null);
    }

    /**
     * Promoted বা Repeated — দুই ক্ষেত্রেই student নতুন সেশনে যায়, তাই ফি সবসময়
     * পরের সেশন বছরের fee structure থেকে আসে।
     */
    private function feeSessionYear(StudentProfile $record): int
    {
        return $record->session_year + 1;
    }

    /**
     * @return array<int, string>
     */
    private function feeStructureOptions(Get $get, StudentProfile $record): array
    {
        return $this->collectPromotionFees()
            ->availableStructures($get('class_id') ? (int) $get('class_id') : null, $this->feeSessionYear($record))
            ->mapWithKeys(fn (FeeStructure $structure): array => [
                $structure->id => $structure->feeType->name
                    .' ('.($structure->feeType->is_monthly ? 'Monthly' : 'One-time').')'
                    .' — ৳'.number_format((float) $structure->amount, 2),
            ])
            ->all();
    }

    /**
     * One action instance per request, so its per-class fee structure lookup
     * is reused by every form closure instead of being re-queried each time.
     */
    private function collectPromotionFees(): CollectPromotionFeesAction
    {
        return $this->collectPromotionFees ??= app(CollectPromotionFeesAction::class);
    }

    private function hasFeeSelected(Get $get): bool
    {
        return array_filter((array) $get('fee_structure_ids')) !== [];
    }

    private function hasMonthlyFeeSelected(Get $get): bool
    {
        $feeStructureIds = array_filter((array) $get('fee_structure_ids'));

        return $feeStructureIds !== []
            && FeeStructure::whereIn('id', $feeStructureIds)
                ->whereHas('feeType', fn ($query) => $query->where('is_monthly', true))
                ->exists();
    }

    private function isReceivingPayment(Get $get): bool
    {
        return $this->hasFeeSelected($get) && (float) $get('amount_paid') > 0;
    }

    private function feePayableTotal(Get $get, StudentProfile $record): float
    {
        if (! $get('class_id') || ! $this->hasFeeSelected($get)) {
            return 0;
        }

        return $this->collectPromotionFees()->payableTotal(
            $record,
            (int) $get('class_id'),
            $this->feeSessionYear($record),
            (array) $get('fee_structure_ids'),
            (array) $get('fee_months'),
        );
    }

    private function syncFeeAmount(Set $set, Get $get, StudentProfile $record): void
    {
        $set('amount_paid', $this->hasFeeSelected($get)
            ? number_format($this->feePayableTotal($get, $record), 2, '.', '')
            : null);
    }

    /**
     * The status Select casts its state to a PromotionStatus instance once touched, but holds
     * the plain string value straight after fillForm() — normalize before comparing either way.
     */
    private function statusValue(Get $get): ?string
    {
        $status = $get('status');

        return $status instanceof PromotionStatus ? $status->value : $status;
    }

    /**
     * Null means there is no active class ahead of the student's current one — i.e. this
     * is the school's terminal class (e.g. Class 10) and the student is graduating, not
     * moving to another class. Callers must not fall back to the student's own class here.
     */
    private function resolveNextClassId(StudentProfile $record): ?int
    {
        $current = $record->class;

        if (! $current) {
            return null;
        }

        return Classes::active()
            ->where('order', '>', $current->order)
            ->orderBy('order')
            ->value('id');
    }

    /**
     * Groups are shared across classes (via the class_groups pivot), so a student's current
     * group_id is the same row the target class would use — reuse it only if the target
     * class actually offers that group; otherwise the admin picks fresh.
     */
    private function resolveDefaultGroupId(StudentProfile $record, ?int $targetClassId): ?int
    {
        if (! $targetClassId || ! $record->current_group_id) {
            return null;
        }

        $targetClass = Classes::find($targetClassId);

        if (! $targetClass?->has_group) {
            return null;
        }

        $hasSameGroup = $targetClass->groups()
            ->where('groups.id', $record->current_group_id)
            ->where('groups.is_active', true)
            ->exists();

        return $hasSameGroup ? $record->current_group_id : null;
    }

    /**
     * @return array{main_optional_subject_id: ?int, extra_optional_subject_id: ?int}
     */
    private function resolveDefaultOptionalSubjects(StudentProfile $record, ?int $classId, ?int $groupId): array
    {
        return app(ResolveDefaultPromotionOptionalSubjects::class)->execute($record, $classId, $groupId);
    }

    private function resolveMeritRoll(StudentProfile $record): ?int
    {
        $mainExamId = Exam::where('class_id', $record->current_class_id)
            ->where('session_year', $record->session_year)
            ->whereHas('examType.examTypeConfig', fn ($query) => $query->where('type', ExamConfigType::Main))
            ->value('id');

        if (! $mainExamId) {
            return null;
        }

        return StudentMeritRanking::where('exam_id', $mainExamId)
            ->where('student_id', $record->id)
            ->value('class_rank');
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
