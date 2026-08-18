<?php

namespace App\Models;

use App\Enums\ClassLevel;
use App\Models\ClassGroupSubject;
use App\Models\Group;
use App\Models\Section;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Classes extends Model
{
    use LogsActivity;
    use LogsRelationLabels;
    use SoftDeletes;

    protected $table = 'classes';

    protected $fillable = ['name', 'level', 'order', 'class_teacher_id', 'has_section', 'has_group', 'is_active'];

    protected $casts = [
        'level' => ClassLevel::class,
        'has_section' => 'boolean',
        'has_group' => 'boolean',
        'is_active' => 'boolean',
    ];

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'class_groups', 'class_id', 'group_id')
            ->using(ClassGroup::class)
            ->withTimestamps();
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'current_class_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'class_id');
    }

    public function classGroupSubjects(): HasMany
    {
        return $this->hasMany(ClassGroupSubject::class, 'class_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_group_subject', 'class_id', 'subject_id')
            ->using(ClassGroupSubject::class)
            ->withPivot('group_id', 'subject_type')
            ->withTimestamps();
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'class_id');
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'class_teacher_id');
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class, 'class_id');
    }

    public function noticeTargets(): MorphMany
    {
        return $this->morphMany(NoticeTarget::class, 'targetable');
    }

    public function subjectsForGroup(?int $groupId): Collection
    {
        return ClassGroupSubject::query()
            ->where('class_id', $this->id)
            ->where(function ($query) use ($groupId) {
                $query->whereNull('group_id');

                if ($groupId !== null) {
                    $query->orWhere('group_id', $groupId);
                }
            })
            ->with('subject')
            ->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('class')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." class \"{$this->name}\".");
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'class_teacher_id' => fn (int|string|null $id): ?string => $id === null ? null : TeacherProfile::find($id)?->user?->name,
        ];
    }
}
