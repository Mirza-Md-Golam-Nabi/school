<?php

namespace App\Models;

use App\Enums\ExamNotificationChannel;
use App\Enums\ExamNotificationKind;
use App\Enums\SubjectType;
use App\Models\StudentMeritRanking;
use App\Support\BengaliNumber;
use App\Traits\HasAcademicSession;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use HasAcademicSession;
    use LogsActivity;
    use LogsRelationLabels;
    use SoftDeletes;

    /**
     * The most rows a two-column admit card routine has in a column before
     * it is split into three columns.
     */
    public const ADMIT_CARD_ROUTINE_ROWS_PER_COLUMN = 8;

    /**
     * @var Collection<int, Collection<int, ClassGroupSubject>>|null
     */
    private ?Collection $syllabusRecordsBySubject = null;

    protected $fillable = [
        'exam_type_id',
        'class_id',
        'session_year',
        'start_date',
        'end_date',
        'is_published',
        'subject_marks_channels',
        'result_channels',
        'notify_result_on_publish',
        'has_custom_subject_merges',
        'result_rule_snapshot',
    ];

    protected $attributes = [
        'notify_result_on_publish' => true,
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_published' => 'boolean',
        'subject_marks_channels' => 'array',
        'result_channels' => 'array',
        'notify_result_on_publish' => 'boolean',
        'has_custom_subject_merges' => 'boolean',
        'session_year' => 'integer',
        'result_rule_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Exam $exam): void {
            $exam->subjectConfigs()->delete();
        });

        static::restoring(function (Exam $exam): void {
            $exam->subjectConfigs()->withTrashed()->restore();
        });
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subjectConfigs(): HasMany
    {
        return $this->hasMany(ExamSubjectConfig::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    /**
     * Subjects counted as one in this exam's result (e.g. Bangla 1st + 2nd paper).
     * They follow the class's default merges until changed on the exam
     * itself (`has_custom_subject_merges`).
     */
    public function subjectMerges(): HasMany
    {
        return $this->hasMany(ExamSubjectMerge::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }

    public function meritRankings(): HasMany
    {
        return $this->hasMany(StudentMeritRanking::class);
    }

    public function notificationBatches(): HasMany
    {
        return $this->hasMany(ExamNotificationBatch::class);
    }

    /**
     * The channels this exam's subject marks go out through — push only
     * until the admin chooses otherwise.
     *
     * @return list<ExamNotificationChannel>
     */
    public function subjectMarksChannels(): array
    {
        return $this->subject_marks_channels === null
            ? [ExamNotificationChannel::Push]
            : ExamNotificationChannel::listFrom($this->subject_marks_channels);
    }

    /**
     * The channels this exam's complete result goes out through — push and
     * SMS until the admin chooses otherwise.
     *
     * @return list<ExamNotificationChannel>
     */
    public function resultChannels(): array
    {
        return $this->result_channels === null
            ? [ExamNotificationChannel::Push, ExamNotificationChannel::Sms]
            : ExamNotificationChannel::listFrom($this->result_channels);
    }

    /**
     * @return list<ExamNotificationChannel>
     */
    public function channelsFor(ExamNotificationKind $kind): array
    {
        return $kind === ExamNotificationKind::SubjectMarks ? $this->subjectMarksChannels() : $this->resultChannels();
    }

    /**
     * A subject's name on this exam's papers — the name written for this
     * exam in its subject config, else the subject's own name.
     */
    public function subjectDisplayName(?Subject $subject): string
    {
        if (! $subject) {
            return '—';
        }

        return $this->subjectConfigs->firstWhere('subject_id', $subject->id)?->subjectDisplayName($subject)
            ?? $subject->display_name;
    }

    /**
     * The exam routine as an admit card prints it: in date order, numbered,
     * split into two columns — the first half (rounded up) on the left, so
     * 7 subjects are 4 + 3. Past ADMIT_CARD_ROUTINE_ROWS_PER_COLUMN rows a
     * column, it is split into three (22 subjects are 8 + 7 + 7), so the card
     * stays short enough for two of them on one A4 page.
     *
     * Dates and subject names follow the current locale, so in বাংলা the
     * month, the digits and the subject (name_bn) are Bengali.
     *
     * Given a student, only the subjects that student takes are listed (see
     * studentTakesScheduledSubject()), so a Science student's card has no
     * Accounting and an optional subject shows only for those who chose it.
     *
     * @return list<list<array{serial: int, date: ?string, subject: string}>>
     */
    public function admitCardRoutineColumns(?StudentProfile $student = null): array
    {
        // Callers eager-load the schedules already in routine order
        // (ExamSchedule::inExamDateOrder()); load them that way if they didn't.
        $this->loadMissing(['schedules' => fn ($query) => $query->inExamDateOrder()->with('subject')]);

        $rows = $this->schedules
            ->filter(fn (ExamSchedule $schedule): bool => $this->studentTakesScheduledSubject($student, $schedule->subject_id))
            ->values()
            ->map(fn (ExamSchedule $schedule, int $index): array => [
                'serial' => $index + 1,
                'date' => $schedule->exam_date ? BengaliNumber::localize($schedule->exam_date->translatedFormat('d M Y')) : null,
                'subject' => $this->subjectDisplayName($schedule->subject),
            ]);

        $columnCount = $rows->count() > 2 * self::ADMIT_CARD_ROUTINE_ROWS_PER_COLUMN ? 3 : 2;
        $rowsPerColumn = intdiv($rows->count(), $columnCount);
        $longerColumns = $rows->count() % $columnCount;

        // The first columns take one row more each, so 7 rows are 4 + 3 and 22 are 8 + 7 + 7.
        return collect(range(0, $columnCount - 1))
            ->map(fn (int $column): array => $rows
                ->slice($column * $rowsPerColumn + min($column, $longerColumns), $rowsPerColumn + ($column < $longerColumns ? 1 : 0))
                ->values()
                ->all())
            ->all();
    }

    /**
     * Whether a student sits this exam's paper in a subject, by the class
     * syllabus (class_group_subjects): a row for the student's group wins over
     * an "All Groups" row; a compulsory subject is taken by everyone it
     * applies to, an optional one only by students who chose it.
     *
     * A subject the syllabus does not list — or no student at all (the design
     * sample) — is kept, so a routine never loses a paper by a missing setup.
     */
    public function studentTakesScheduledSubject(?StudentProfile $student, int|string|null $subjectId): bool
    {
        if (! $student?->exists || $subjectId === null || ! $this->class_id) {
            return true;
        }

        $records = $this->syllabusRecordsBySubject()->get((int) $subjectId);

        if ($records === null || $records->isEmpty()) {
            return true;
        }

        $groupId = $student->current_group_id ? (int) $student->current_group_id : null;

        $record = $records->first(fn (ClassGroupSubject $record): bool => $record->group_id !== null && (int) $record->group_id === $groupId)
            ?? $records->first(fn (ClassGroupSubject $record): bool => $record->group_id === null);

        if (! $record) {
            return false;
        }

        if ($record->subject_type === SubjectType::Compulsory) {
            return true;
        }

        // A paper's optional choice is stored against its main subject, the
        // subject_id its syllabus record carries.
        $student->loadMissing('optionalSubjects');

        return $student->optionalSubjects->contains(fn (StudentOptionalSubject $choice): bool => (int) $choice->subject_id === (int) $record->subject_id
            && (int) $choice->class_id === (int) $this->class_id);
    }

    /**
     * The class syllabus by subject, read once per exam instance — the class
     * sheet renders every student's card from the same eager-loaded exam.
     *
     * @return Collection<int, Collection<int, ClassGroupSubject>>
     */
    private function syllabusRecordsBySubject(): Collection
    {
        return $this->syllabusRecordsBySubject ??= ClassGroupSubject::recordsBySubjectForClass((int) $this->class_id);
    }

    public function displayLabel(): string
    {
        return "{$this->examType?->name} - {$this->class?->name} ({$this->session_label})";
    }

    /**
     * displayLabel() of an exam (trashed included), resolved once per request
     * for the activity log.
     */
    public static function activityLabelFor(int|string|null $examId): ?string
    {
        if ($examId === null) {
            return null;
        }

        return self::cachedActivityLabel(
            "exam_label|{$examId}",
            fn (): ?string => self::withTrashed()->with(['examType', 'class'])->find($examId)?->displayLabel(),
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            // A copy of the result rule, saved by the result calculation — not an edit.
            ->logExcept(['result_rule_snapshot', 'has_custom_subject_merges'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('exam')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." exam \"{$this->displayLabel()}\".");
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_type_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(ExamType::class, $id),
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
        ];
    }
}
