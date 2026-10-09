<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentFeeDiscount extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'student_id',
        'fee_type_id',
        'discount_id',
        'session_year',
        'approved_by',
        'remarks',
    ];

    protected $casts = [
        'session_year' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(FeeDiscount::class, 'discount_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('student_fee_discount')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'student_id' => fn (int|string|null $id): ?string => $id === null ? null : self::studentLabel($id),
            'fee_type_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(FeeType::class, $id),
            'discount_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(FeeDiscount::class, $id),
            'approved_by' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(User::class, $id),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $studentLabel = self::studentLabel($this->student_id) ?? "Student #{$this->student_id}";
        $discountLabel = self::activityNameLabel(FeeDiscount::class, $this->discount_id) ?? "Discount #{$this->discount_id}";
        $feeTypeLabel = self::activityNameLabel(FeeType::class, $this->fee_type_id) ?? "Fee Type #{$this->fee_type_id}";

        return ucfirst($eventName)." fee discount \"{$discountLabel}\" for \"{$studentLabel}\" on \"{$feeTypeLabel}\".";
    }

    private static function studentLabel(int|string $studentId): ?string
    {
        return self::cachedActivityLabel("student_label_with_class|{$studentId}", function () use ($studentId): ?string {
            $student = StudentProfile::withTrashed()
                ->with(['user:id,name', 'class:id,name'])
                ->find($studentId, ['id', 'user_id', 'roll_no', 'current_class_id']);

            if (! $student) {
                return null;
            }

            $name = trim("{$student->user?->name} (Roll: {$student->roll_no})");

            if ($student->current_class_id === null) {
                return $name;
            }

            $classLabel = $student->class?->name ?? "Class #{$student->current_class_id}";

            return "{$classLabel} - {$name}";
        });
    }
}
