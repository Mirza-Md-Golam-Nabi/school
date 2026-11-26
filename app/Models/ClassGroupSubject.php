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

    /**
     * The class_group_subject table has its own auto-incrementing `id`
     * primary key (not just the class_id/group_id/subject_id composite).
     * Pivot defaults this to false, which skips capturing the generated id
     * after insert — breaking any later re-select by key (e.g. Activitylog's
     * fresh() call after a "created" event) when this model is queried
     * directly (firstOrCreate/create) rather than through the belongsToMany
     * relationship that would otherwise set the pivot keys for us.
     */
    public $incrementing = true;

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

    public function isOptional(): bool
    {
        return $this->subject_type === SubjectType::Optional;
    }

    /**
     * Subjects taught in the class — the syllabus subjects plus their papers
     * (a teacher can be given just Biology 1st Paper).
     *
     * @return Collection<int, string>
     */
    public static function dropdownOptionsByClass(int $classId): Collection
    {
        return Subject::query()
            ->taughtInClass($classId)
            ->ordered()
            ->get()
            ->pluck('display_name', 'id');
    }

    /**
     * Whether a student of the given group takes the subject, by its syllabus
     * records (from recordsBySubjectForClass): a record for every student
     * (no group) or for that group. A subject with no records in the class
     * counts as taken, so a subject added to an exam outside the syllabus
     * behaves as before.
     *
     * @param  ?Collection<int, ClassGroupSubject>  $records
     */
    public static function isTakenByGroup(?Collection $records, ?int $groupId): bool
    {
        if ($records === null || $records->isEmpty()) {
            return true;
        }

        return $records->contains(fn (ClassGroupSubject $record): bool => $record->group_id === null
            || ($groupId !== null && (int) $record->group_id === $groupId));
    }

    /**
     * subject_id → the class's records for that subject. A paper the syllabus
     * does not list itself (Biology 1st Paper) gets its main subject's records
     * (Biology), so it shares the main subject's group and compulsory /
     * optional type — and their subject_id points at the main subject, the
     * one a student's optional choice is stored against.
     *
     * @return Collection<int, Collection<int, ClassGroupSubject>>
     */
    public static function recordsBySubjectForClass(int $classId): Collection
    {
        $recordsBySubject = static::query()
            ->where('class_id', $classId)
            ->get()
            ->groupBy('subject_id')
            ->toBase();

        if ($recordsBySubject->isEmpty()) {
            return $recordsBySubject;
        }

        Subject::withTrashed()
            ->whereIn('parent_subject_id', $recordsBySubject->keys())
            ->pluck('parent_subject_id', 'id')
            ->each(function (int|string $parentId, int|string $paperId) use ($recordsBySubject): void {
                if (! $recordsBySubject->has((int) $paperId)) {
                    $recordsBySubject->put((int) $paperId, $recordsBySubject->get((int) $parentId));
                }
            });

        return $recordsBySubject;
    }

    /**
     * A class+group's optional subject pool (subject_type = optional) — the
     * list a student picks their main_optional/extra_optional from. Shared by
     * the student profile form and the promotion pages so both resolve the
     * same pool the same way.
     *
     * main_optional শুধু ওই group-এর নিজস্ব optional subject থেকে বাছা যায়
     * ($includeAllGroups = false), কিন্তু extra_optional-এর জন্য group-specific
     * subject-এর পাশাপাশি "All Groups" (group_id = null) optional subject-ও
     * দেখানো হয় ($includeAllGroups = true, ডিফল্ট)।
     *
     * @return Collection<int, string>
     */
    public static function optionalSubjectOptions(?int $classId, ?int $groupId, bool $includeAllGroups = true): Collection
    {
        if (! $classId || ! $groupId) {
            return collect();
        }

        return static::query()
            ->where('class_id', $classId)
            ->where('subject_type', SubjectType::Optional)
            ->where(function ($query) use ($groupId, $includeAllGroups) {
                $query->where('group_id', $groupId);

                if ($includeAllGroups) {
                    $query->orWhereNull('group_id');
                }
            })
            ->with('subject')
            ->get()
            ->pluck('subject.display_name', 'subject_id');
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
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
            'group_id' => fn (int|string|null $id): ?string => $id === null ? 'All Groups' : self::activityNameLabel(Group::class, $id),
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $classLabel = self::activityNameLabel(Classes::class, $this->class_id) ?? "Class #{$this->class_id}";
        $subjectLabel = self::activityNameLabel(Subject::class, $this->subject_id) ?? "Subject #{$this->subject_id}";
        $groupLabel = $this->group_id === null ? 'All Groups' : (self::activityNameLabel(Group::class, $this->group_id) ?? "Group #{$this->group_id}");

        return match ($eventName) {
            'created' => "Attached subject \"{$subjectLabel}\" to class \"{$classLabel}\" ({$groupLabel}).",
            'deleted' => "Detached subject \"{$subjectLabel}\" from class \"{$classLabel}\" ({$groupLabel}).",
            default => "Updated subject \"{$subjectLabel}\" attachment on class \"{$classLabel}\" ({$groupLabel}).",
        };
    }
}
