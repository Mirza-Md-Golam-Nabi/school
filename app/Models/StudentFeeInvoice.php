<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Traits\LogsRelationLabels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentFeeInvoice extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'student_id',
        'fee_type_id',
        'month',
        'year',
        'original_amount',
        'discount_amount',
        'fine_amount',
        'waiver_amount',
        'net_amount',
        'waiver_by',
        'waiver_reason',
        'status',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'waiver_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'status' => InvoiceStatus::class,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiver_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class, 'invoice_id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function scopePayable(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('student_fee_invoice')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'student_id' => fn (int|string|null $id): ?string => $id === null ? null : self::studentLabel($id),
            'fee_type_id' => fn (int|string|null $id): ?string => $id === null ? null : FeeType::find($id)?->name,
            'waiver_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
            'status' => fn (?string $value): ?string => $value === null ? null : InvoiceStatus::tryFrom($value)?->getLabel(),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $studentLabel = self::studentLabel($this->student_id) ?? "Student #{$this->student_id}";
        $feeTypeLabel = $this->feeType?->name ?? "Fee Type #{$this->fee_type_id}";
        $periodLabel = $this->month
            ? Carbon::create()->month($this->month)->format('F').' '.$this->year
            : (string) $this->year;

        return ucfirst($eventName)." fee invoice for \"{$studentLabel}\" - {$feeTypeLabel} ({$periodLabel}).";
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
