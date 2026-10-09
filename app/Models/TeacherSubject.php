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
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
            'section_id' => fn (int|string|null $id): ?string => $id === null ? 'No Section' : self::activityNameLabel(Section::class, $id),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $teacherLabel = $this->teacher?->user?->name ?? "Teacher #{$this->teacher_id}";
        $subjectLabel = self::activityNameLabel(Subject::class, $this->subject_id) ?? "Subject #{$this->subject_id}";
        $classLabel = self::activityNameLabel(Classes::class, $this->class_id) ?? "Class #{$this->class_id}";
        $sectionLabel = $this->section_id === null ? 'No Section' : (self::activityNameLabel(Section::class, $this->section_id) ?? "Section #{$this->section_id}");

        return match ($eventName) {
            'created' => "Assigned teacher \"{$teacherLabel}\" to teach \"{$subjectLabel}\" in class \"{$classLabel}\" ({$sectionLabel}).",
            'deleted' => "Unassigned teacher \"{$teacherLabel}\" from \"{$subjectLabel}\" in class \"{$classLabel}\" ({$sectionLabel}).",
            default => "Updated teacher \"{$teacherLabel}\" assignment for \"{$subjectLabel}\" in class \"{$classLabel}\" ({$sectionLabel}).",
        };
    }
}
