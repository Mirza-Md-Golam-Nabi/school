<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentResult extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'exam_id',
        'subject_id',
        'class_id',
        'student_id',
        'mcq_marks',
        'written_marks',
        'practical_marks',
        'total_marks',
        'is_absent',
        'contributed_marks',
        'contribution_percent',
        'contribution_source_exam_type_id',
        'contribution_source_breakdown',
        'final_marks',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
        'mcq_marks' => 'float',
        'written_marks' => 'float',
        'practical_marks' => 'float',
        'total_marks' => 'float',
        'contributed_marks' => 'float',
        'contribution_percent' => 'integer',
        'contribution_source_breakdown' => 'array',
        'final_marks' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (StudentResult $result): void {
            if ($result->is_absent) {
                $result->mcq_marks = null;
                $result->written_marks = null;
                $result->practical_marks = null;
                $result->total_marks = 0;
            } else {
                $result->total_marks = round(
                    (float) ($result->mcq_marks ?? 0)
                        + (float) ($result->written_marks ?? 0)
                        + (float) ($result->practical_marks ?? 0),
                    2
                );
            }
        });
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function contributionSourceExamType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'contribution_source_exam_type_id');
    }

    /**
     * Marks to use for ranking/GPA/display: the contribution-blended value when a
     * rule applied, otherwise this exam's own total.
     */
    public function getEffectiveMarksAttribute(): float
    {
        return (float) ($this->final_marks ?? $this->total_marks);
    }

    /**
     * The full-marks scale to compare `effective_marks` against. When a contribution
     * rule applied, the subject's own configured total no longer represents 100% —
     * it only represents (100 - contribution_percent)% of the grand total.
     */
    public function resolveFullMarks(float $ownTotalMarks): float
    {
        if (! $this->contribution_percent) {
            return $ownTotalMarks;
        }

        return $ownTotalMarks / (1 - ($this->contribution_percent / 100));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('student_result')
            ->setDescriptionForEvent(fn (): string => $this->activityLogDescription());
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_id' => fn (int|string|null $id): ?string => $id === null ? null : Exam::withTrashed()->with(['examType', 'class'])->find($id)?->displayLabel(),
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : Classes::find($id)?->name,
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : Subject::find($id)?->name,
            'student_id' => fn (int|string|null $id): ?string => $id === null ? null : self::studentLabel($id),
            'contribution_source_exam_type_id' => fn (int|string|null $id): ?string => $id === null ? null : ExamType::find($id)?->name,
        ];
    }

    private function activityLogDescription(): string
    {
        $exam = Exam::withTrashed()->with('examType')->find($this->exam_id);
        $studentLabel = self::studentLabel($this->student_id) ?? "Student #{$this->student_id}";
        $subjectLabel = Subject::find($this->subject_id)?->name ?? "Subject #{$this->subject_id}";
        $classLabel = Classes::find($this->class_id)?->name ?? "Class #{$this->class_id}";
        $examTypeLabel = $exam?->examType?->name ?? "Exam #{$this->exam_id}";

        $classWithYear = $exam?->session_year !== null ? "{$classLabel} ({$exam->session_year})" : $classLabel;

        return "{$classWithYear} - {$examTypeLabel} - {$subjectLabel} - {$studentLabel}";
    }

    private static function studentLabel(int|string $studentId): ?string
    {
        $student = StudentProfile::withTrashed()->with('user')->find($studentId);

        if (! $student) {
            return null;
        }

        return trim("{$student->user?->name} (Roll: {$student->roll_no})");
    }
}
