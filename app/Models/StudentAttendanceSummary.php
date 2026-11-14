<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A student's total attendance up to one exam, written directly by the class
 * teacher or an admin (e.g. when most days were taken on paper). Where it
 * exists, the marksheet and attendance rankings show it instead of the count
 * of daily attendance rows.
 */
class StudentAttendanceSummary extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'exam_id',
        'student_id',
        'class_id',
        'working_days',
        'present_days',
        'entered_by',
    ];

    protected $casts = [
        'working_days' => 'integer',
        'present_days' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['working_days', 'present_days', 'exam_id', 'student_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('student_attendance_summary')
            ->setDescriptionForEvent(function (string $eventName): string {
                $examLabel = Exam::activityLabelFor($this->exam_id) ?? "Exam #{$this->exam_id}";
                $studentLabel = self::studentActivityLabel($this->student_id) ?? "Student #{$this->student_id}";

                return ucfirst($eventName)." total attendance for \"{$studentLabel}\" in \"{$examLabel}\" ({$this->present_days}/{$this->working_days}).";
            });
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_id' => fn (int|string|null $id): ?string => Exam::activityLabelFor($id),
            'student_id' => fn (int|string|null $id): ?string => self::studentActivityLabel($id),
        ];
    }

    /**
     * "Name (Roll: 7)" of a student, looked up once per request.
     */
    private static function studentActivityLabel(int|string|null $studentId): ?string
    {
        if ($studentId === null) {
            return null;
        }

        return self::cachedActivityLabel("student_label|{$studentId}", function () use ($studentId): ?string {
            $student = StudentProfile::withTrashed()->with('user:id,name')->find($studentId);

            return $student ? "{$student->user?->name} (Roll: {$student->roll_no})" : null;
        });
    }
}
