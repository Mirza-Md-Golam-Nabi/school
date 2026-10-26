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
        'result_breakdown',
        'final_marks',
        'final_full_marks',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
        'mcq_marks' => 'float',
        'written_marks' => 'float',
        'practical_marks' => 'float',
        'total_marks' => 'float',
        'result_breakdown' => 'array',
        'final_marks' => 'float',
        'final_full_marks' => 'float',
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

    /**
     * Marks to use for ranking/GPA/display: the result rule's weighted marks
     * where one built this result, otherwise this exam's own total.
     */
    public function getEffectiveMarksAttribute(): float
    {
        return (float) ($this->final_marks ?? $this->total_marks);
    }

    /**
     * Whether a result rule built this result from several exams
     * (CalculateWeightedResults) — then `final_marks` / `final_full_marks`
     * and `result_breakdown` hold it.
     */
    public function hasWeightedResult(): bool
    {
        return $this->final_marks !== null;
    }

    /**
     * The full marks `effective_marks` is out of: the rule's full marks for a
     * weighted result, otherwise the subject's own total in this exam.
     */
    public function resolveFullMarks(float $ownTotalMarks): float
    {
        return $this->hasWeightedResult() ? (float) $this->final_full_marks : $ownTotalMarks;
    }

    /**
     * Whether the student missed the pass mark of a result-rule component
     * that has to be passed on its own.
     */
    public function failsARequiredComponent(): bool
    {
        return collect($this->result_breakdown ?? [])->contains(fn (array $component): bool => ($component['passed'] ?? null) === false);
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
            'exam_id' => fn (int|string|null $id): ?string => Exam::activityLabelFor($id),
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
            'student_id' => fn (int|string|null $id): ?string => $id === null ? null : self::studentLabel($id),
        ];
    }

    private function activityLogDescription(): string
    {
        $exam = self::cachedActivityLabel(
            "student_result_exam|{$this->exam_id}",
            fn (): ?Exam => Exam::withTrashed()->with('examType')->find($this->exam_id),
        );
        $studentLabel = self::studentLabel($this->student_id) ?? "Student #{$this->student_id}";
        $subjectLabel = self::activityNameLabel(Subject::class, $this->subject_id) ?? "Subject #{$this->subject_id}";
        $classLabel = self::activityNameLabel(Classes::class, $this->class_id) ?? "Class #{$this->class_id}";
        $examTypeLabel = $exam?->examType?->name ?? "Exam #{$this->exam_id}";

        $classWithYear = $exam?->session_year !== null ? "{$classLabel} ({$exam->session_year})" : $classLabel;

        return "{$classWithYear} - {$examTypeLabel} - {$subjectLabel} - {$studentLabel}";
    }

    private static function studentLabel(int|string $studentId): ?string
    {
        return self::cachedActivityLabel("student_label|{$studentId}", function () use ($studentId): ?string {
            $student = StudentProfile::withTrashed()->with('user:id,name')->find($studentId, ['id', 'user_id', 'roll_no']);

            if (! $student) {
                return null;
            }

            return trim("{$student->user?->name} (Roll: {$student->roll_no})");
        });
    }
}
