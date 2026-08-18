<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClassGroup extends Pivot
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $table = 'class_groups';

    protected $fillable = [
        'class_id',
        'group_id',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('class_group')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : Classes::find($id)?->name,
            'group_id' => fn (int|string|null $id): ?string => $id === null ? null : Group::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $classLabel = $this->schoolClass?->name ?? "Class #{$this->class_id}";
        $groupLabel = $this->group?->name ?? "Group #{$this->group_id}";

        return match ($eventName) {
            'created' => "Attached group \"{$groupLabel}\" to class \"{$classLabel}\".",
            'deleted' => "Detached group \"{$groupLabel}\" from class \"{$classLabel}\".",
            default => "Updated group \"{$groupLabel}\" attachment on class \"{$classLabel}\".",
        };
    }
}
