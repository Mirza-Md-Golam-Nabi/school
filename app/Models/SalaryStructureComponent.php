<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalaryStructureComponent extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'salary_structure_id',
        'salary_component_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('salary_structure_component')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'salary_component_id' => fn (int|string|null $id): ?string => $id === null ? null : SalaryComponent::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $componentLabel = $this->component?->name ?? "Component #{$this->salary_component_id}";
        $structureLabel = $this->resolveStructureLabel();
        $amountLabel = number_format((float) $this->amount, 2);

        return match ($eventName) {
            'created' => "Added \"{$componentLabel}\" (৳{$amountLabel}) to the salary structure for \"{$structureLabel}\".",
            'deleted' => "Removed \"{$componentLabel}\" from the salary structure for \"{$structureLabel}\".",
            default => "Updated \"{$componentLabel}\" (৳{$amountLabel}) in the salary structure for \"{$structureLabel}\".",
        };
    }

    private function resolveStructureLabel(): string
    {
        $profileable = $this->structure?->profileable;

        if ($profileable instanceof TeacherProfile || $profileable instanceof StaffProfile) {
            return $profileable->user?->name ?? class_basename($profileable)." #{$this->structure?->profileable_id}";
        }

        return "Structure #{$this->salary_structure_id}";
    }
}
