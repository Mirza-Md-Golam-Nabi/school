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
            'fee_type_id' => fn (int|string|null $id): ?string => $id === null ? null : FeeType::find($id)?->name,
            'discount_id' => fn (int|string|null $id): ?string => $id === null ? null : FeeDiscount::find($id)?->name,
            'approved_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $studentLabel = self::studentLabel($this->student_id) ?? "Student #{$this->student_id}";
        $discountLabel = $this->discount?->name ?? "Discount #{$this->discount_id}";
        $feeTypeLabel = $this->feeType?->name ?? "Fee Type #{$this->fee_type_id}";

        return ucfirst($eventName)." fee discount \"{$discountLabel}\" for \"{$studentLabel}\" on \"{$feeTypeLabel}\".";
    }

    private static function studentLabel(int|string $studentId): ?string
    {
        $student = StudentProfile::withTrashed()->with(['user', 'class'])->find($studentId);

        if (! $student) {
            return null;
        }

        $name = trim("{$student->user?->name} (Roll: {$student->roll_no})");

        if ($student->current_class_id === null) {
            return $name;
        }

        $classLabel = $student->class?->name ?? "Class #{$student->current_class_id}";

        return "{$classLabel} - {$name}";
    }
}
