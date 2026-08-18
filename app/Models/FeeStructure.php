<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeStructure extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'class_id',
        'fee_type_id',
        'amount',
        'due_day',
        'session_year',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_day' => 'integer',
        'session_year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('fee_structure')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : Classes::find($id)?->name,
            'fee_type_id' => fn (int|string|null $id): ?string => $id === null ? null : FeeType::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $classLabel = $this->class?->name ?? "Class #{$this->class_id}";
        $feeTypeLabel = $this->feeType?->name ?? "Fee Type #{$this->fee_type_id}";

        return ucfirst($eventName)." fee structure for \"{$feeTypeLabel}\" - {$classLabel} ({$this->session_year}).";
    }
}
