<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use App\Traits\OrdersBySubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentResult extends Model
{
    use LogsActivity;
    use LogsRelationLabels;
    use OrdersBySubject;

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
        'is_theory_absent',
        'is_practical_absent',
        'result_breakdown',
        'final_marks',
        'final_full_marks',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
        'is_theory_absent' => 'boolean',
        'is_practical_absent' => 'boolean',
        'mcq_marks' => 'float',
        'written_marks' => 'float',
        'practical_marks' => 'float',
        'total_marks' => 'float',
        'result_breakdown' => 'array',
        'final_marks' => 'float',
        'final_full_marks' => 'float',
    ];

    /**
     * `is_absent` means absent in the whole subject — the same as absent in
     * both the theory (MCQ + written) and the practical exam. Marking the
     * subject absent marks both parts absent; marking both parts absent marks
     * the subject absent, unless `is_absent` was just turned off, which clears
     * both parts. An absent part's marks are dropped.
     */
    protected static function booted(): void
    {
        static::saving(function (StudentResult $result): void {
            if ($result->is_absent) {
                $result->is_theory_absent = true;
                $result->is_practical_absent = true;
            } elseif ($result->is_theory_absent && $result->is_practical_absent) {
                if ($result->exists && $result->isDirty('is_absent')) {
                    $result->is_theory_absent = false;
                    $result->is_practical_absent = false;
                } else {
                    $result->is_absent = true;
                }
            }

            if ($result->is_theory_absent) {
                $result->mcq_marks = null;
                $result->written_marks = null;
            }

            if ($result->is_practical_absent) {
                $result->practical_marks = null;
            }

            $result->total_marks = round(
                (float) ($result->mcq_marks ?? 0)
                    + (float) ($result->written_marks ?? 0)
                    + (float) ($result->practical_marks ?? 0),
                2
            );
        });
    }

    /**
     * Whether the student missed the subject, or only its theory (MCQ +
     * written) or practical exam — any of which fails the subject.
     */
    public function isAbsentInAnyPart(): bool
    {
        return $this->is_absent || $this->is_theory_absent || $this->is_practical_absent;
    }

    /**
     * The result attributes from one student's row on the Enter Marks page:
     * blank marks become null, an absent part's marks are dropped. Per part,
     * the theory (MCQ + written) and practical checkboxes are read and absent
     * in both is absent in the subject; otherwise the one Absent checkbox
     * stands for both parts.
     *
     * @param  array<string, mixed>  $mark
     * @return array{mcq_marks: mixed, written_marks: mixed, practical_marks: mixed, is_absent: bool, is_theory_absent: bool, is_practical_absent: bool}
     */
    public static function markEntryAttributes(array $mark, bool $absenceIsPerPart): array
    {
        if ($absenceIsPerPart) {
            $isTheoryAbsent = (bool) ($mark['is_theory_absent'] ?? false);
            $isPracticalAbsent = (bool) ($mark['is_practical_absent'] ?? false);
        } else {
            $isTheoryAbsent = $isPracticalAbsent = (bool) ($mark['is_absent'] ?? false);
        }

        $value = fn (string $field, bool $isPartAbsent): mixed => $isPartAbsent || ($mark[$field] ?? '') === '' ? null : $mark[$field];

        return [
            'mcq_marks' => $value('mcq_marks', $isTheoryAbsent),
            'written_marks' => $value('written_marks', $isTheoryAbsent),
            'practical_marks' => $value('practical_marks', $isPracticalAbsent),
            'is_absent' => $isTheoryAbsent && $isPracticalAbsent,
            'is_theory_absent' => $isTheoryAbsent,
            'is_practical_absent' => $isPracticalAbsent,
        ];
    }

    /**
     * Whether the student missed only one part (theory or practical) and sat
     * the other.
     */
    public function isPartlyAbsent(): bool
    {
        return ! $this->is_absent && ($this->is_theory_absent || $this->is_practical_absent);
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
