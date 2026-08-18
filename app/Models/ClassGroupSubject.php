<?php

namespace App\Models;

use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Subject;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClassGroupSubject extends Pivot
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'class_id',
        'group_id',
        'subject_id',
        'subject_type',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'subject_type' => SubjectType::class,
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * group_id = NULL means for all groups
     */
    public function isForAllGroups(): bool
    {
        return is_null($this->group_id);
    }

    public function isCompulsory(): bool
    {
        return $this->subject_type === SubjectType::Compulsory;
    }

    public function isMainOptional(): bool
    {
        return $this->subject_type === SubjectType::MainOptional;
    }

    public function isExtraOptional(): bool
    {
        return $this->subject_type === SubjectType::ExtraOptional;
    }

    public static function dropdownOptionsByClass(int $classId): Collection
    {
        return static::query()
            ->where('class_id', $classId)
            ->with('subject')
            ->get()
            ->pluck('subject.name', 'subject_id')
            ->unique()
            ->filter();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('class_subject')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : Classes::find($id)?->name,
            'group_id' => fn (int|string|null $id): ?string => $id === null ? 'All Groups' : Group::find($id)?->name,
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : Subject::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $classLabel = $this->schoolClass?->name ?? "Class #{$this->class_id}";
        $subjectLabel = $this->subject?->name ?? "Subject #{$this->subject_id}";
        $groupLabel = $this->group_id === null ? 'All Groups' : ($this->group?->name ?? "Group #{$this->group_id}");

        return match ($eventName) {
            'created' => "Attached subject \"{$subjectLabel}\" to class \"{$classLabel}\" ({$groupLabel}).",
            'deleted' => "Detached subject \"{$subjectLabel}\" from class \"{$classLabel}\" ({$groupLabel}).",
            default => "Updated subject \"{$subjectLabel}\" attachment on class \"{$classLabel}\" ({$groupLabel}).",
        };
    }
}
