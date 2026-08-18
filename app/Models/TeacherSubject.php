<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TeacherSubject extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'class_id',
        'section_id',
        'session_year',
    ];

    protected $casts = [
        'session_year' => 'integer',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('teacher_subject')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'teacher_id' => fn (int|string|null $id): ?string => $id === null ? null : TeacherProfile::find($id)?->user?->name,
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : Subject::find($id)?->name,
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : Classes::find($id)?->name,
            'section_id' => fn (int|string|null $id): ?string => $id === null ? 'No Section' : Section::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $teacherLabel = $this->teacher?->user?->name ?? "Teacher #{$this->teacher_id}";
        $subjectLabel = $this->subject?->name ?? "Subject #{$this->subject_id}";
        $classLabel = $this->class?->name ?? "Class #{$this->class_id}";
        $sectionLabel = $this->section_id === null ? 'No Section' : ($this->section?->name ?? "Section #{$this->section_id}");

        return match ($eventName) {
            'created' => "Assigned teacher \"{$teacherLabel}\" to teach \"{$subjectLabel}\" in class \"{$classLabel}\" ({$sectionLabel}).",
            'deleted' => "Unassigned teacher \"{$teacherLabel}\" from \"{$subjectLabel}\" in class \"{$classLabel}\" ({$sectionLabel}).",
            default => "Updated teacher \"{$teacherLabel}\" assignment for \"{$subjectLabel}\" in class \"{$classLabel}\" ({$sectionLabel}).",
        };
    }
}
