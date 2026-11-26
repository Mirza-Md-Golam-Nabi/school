<?php

namespace App\Models;

use App\Actions\MarkExamDocumentsOutdatedAction;
use App\Actions\SyncExamPaperMergesAction;
use App\Traits\LogsRelationLabels;
use App\Traits\OrdersBySubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExamSubjectConfig extends Model
{
    use LogsActivity;
    use LogsRelationLabels;
    use OrdersBySubject;
    use SoftDeletes;

    protected $fillable = [
        'exam_id',
        'subject_id',
        'subject_name',
        'subject_name_bn',
        'mcq_total',
        'mcq_pass_mark',
        'written_total',
        'written_pass_mark',
        'practical_total',
        'practical_pass_mark',
        'total_marks',
        'pass_mark',
        'check_mcq_pass',
        'check_written_pass',
        'check_practical_pass',
        'contributes_to_target',
    ];

    protected $casts = [
        'check_mcq_pass' => 'boolean',
        'check_written_pass' => 'boolean',
        'check_practical_pass' => 'boolean',
        'contributes_to_target' => 'boolean',
    ];

    /**
     * A paper (Biology 1st Paper) joining the exam merges it with its other
     * paper; one leaving unmerges them. Changing any other subject leaves the
     * merges alone, so a paper merge the admin deleted does not come back.
     */
    protected static function booted(): void
    {
        $syncPaperMerges = function (ExamSubjectConfig $config, array $addedSubjectIds = []): void {
            if ($config->exam) {
                app(SyncExamPaperMergesAction::class)->handle($config->exam, $addedSubjectIds);
            }
        };

        static::created(fn (ExamSubjectConfig $config) => $syncPaperMerges($config, [(int) $config->subject_id]));
        static::updated(function (ExamSubjectConfig $config) use ($syncPaperMerges): void {
            if ($config->wasChanged('subject_id')) {
                $syncPaperMerges($config, [(int) $config->subject_id]);
            }

            // PDFs already made for the exam still print the old name.
            if ($config->wasChanged(['subject_name', 'subject_name_bn']) && $config->exam) {
                app(MarkExamDocumentsOutdatedAction::class)->handle($config->exam);
            }
        });
        static::deleted(fn (ExamSubjectConfig $config) => $syncPaperMerges($config));
        static::restored(fn (ExamSubjectConfig $config) => $syncPaperMerges($config, [(int) $config->subject_id]));
    }

    /**
     * The subject's name on this exam's papers (marksheet, tabulation sheet,
     * result report, admit card routine, exam routine): the name written for
     * this exam, else the subject's own name. On a Bangla document the Bangla
     * name written for the exam comes first, then the English one.
     */
    public function subjectDisplayName(?Subject $subject = null): string
    {
        $examName = app()->getLocale() === 'bn'
            ? ($this->subject_name_bn ?: $this->subject_name)
            : $this->subject_name;

        if (filled($examName)) {
            return $examName;
        }

        return ($subject ?? $this->subject)?->display_name ?? '—';
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Whether the student passed this subject by its pass marks: the overall
     * pass mark, and the MCQ / written / practical pass marks where those are
     * checked on their own. Absent never passes — neither does absent in only
     * the theory (MCQ + written) or the practical exam.
     */
    public function isPassedBy(StudentResult $result): bool
    {
        if ($result->isAbsentInAnyPart()) {
            return false;
        }

        if ($this->pass_mark !== null && (float) $result->total_marks < (float) $this->pass_mark) {
            return false;
        }

        return $this->passesPartMarks($result);
    }

    /**
     * Whether the result reaches the MCQ / written / practical pass marks
     * that are checked on their own (the overall pass mark is not looked at).
     */
    public function passesPartMarks(StudentResult $result): bool
    {
        foreach ($this->checkedPartPassMarks() as $part => $passMark) {
            if ((float) $result->{"{$part}_marks"} < $passMark) {
                return false;
            }
        }

        return true;
    }

    /**
     * The parts (mcq / written / practical) whose pass mark is checked on
     * its own, with that pass mark.
     *
     * @return array<string, float>
     */
    public function checkedPartPassMarks(): array
    {
        return collect([
            'mcq' => [$this->check_mcq_pass, $this->mcq_pass_mark],
            'written' => [$this->check_written_pass, $this->written_pass_mark],
            'practical' => [$this->check_practical_pass, $this->practical_pass_mark],
        ])
            ->filter(fn (array $part): bool => $part[0] && $part[1] !== null)
            ->map(fn (array $part): float => (float) $part[1])
            ->all();
    }

    /**
     * The overall pass mark as a percentage of the subject's total — how a
     * pass is judged where several exams' marks are combined into one.
     */
    public function passPercentage(): float
    {
        return (float) $this->total_marks > 0 ? (float) $this->pass_mark / (float) $this->total_marks * 100 : 0.0;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('exam_subject_config')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_id' => fn (int|string|null $id): ?string => Exam::activityLabelFor($id),
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $subjectLabel = self::activityNameLabel(Subject::class, $this->subject_id) ?? "Subject #{$this->subject_id}";
        $examLabel = Exam::activityLabelFor($this->exam_id) ?? "Exam #{$this->exam_id}";

        return ucfirst($eventName)." subject config for \"{$subjectLabel}\" in \"{$examLabel}\".";
    }
}
