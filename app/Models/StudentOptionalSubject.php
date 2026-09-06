<?php

namespace App\Models;

use App\Enums\OptionalSubjectRole;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentOptionalSubject extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'student_id',
        'class_id',
        'group_id',
        'subject_id',
        'role',
    ];

    protected $casts = [
        'role' => OptionalSubjectRole::class,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

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

    public function isMainOptional(): bool
    {
        return $this->role === OptionalSubjectRole::MainOptional;
    }

    public function isExtraOptional(): bool
    {
        return $this->role === OptionalSubjectRole::ExtraOptional;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('student_optional_subject')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'student_id' => fn (int|string|null $id): ?string => $id === null ? null : StudentProfile::withTrashed()->with('user')->find($id)?->user?->name,
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : Classes::find($id)?->name,
            'group_id' => fn (int|string|null $id): ?string => $id === null ? null : Group::find($id)?->name,
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : Subject::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $studentLabel = $this->student?->user?->name ?? "Student #{$this->student_id}";
        $subjectLabel = $this->subject?->name ?? "Subject #{$this->subject_id}";
        $roleLabel = $this->role?->getLabel() ?? $this->role?->value;

        return match ($eventName) {
            'created' => "Set \"{$subjectLabel}\" as {$roleLabel} for \"{$studentLabel}\".",
            'deleted' => "Removed \"{$subjectLabel}\" as {$roleLabel} for \"{$studentLabel}\".",
            default => "Updated {$roleLabel} subject for \"{$studentLabel}\" to \"{$subjectLabel}\".",
        };
    }
}
