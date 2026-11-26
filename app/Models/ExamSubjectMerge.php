<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use App\Traits\MergesTwoSubjects;
use Database\Factories\ExamSubjectMergeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One exam's own subject merge — copied from the class's SubjectMerge when
 * the exam is created, with every rule filled in, so later changes to the
 * class default or School Settings never change this exam's result.
 */
class ExamSubjectMerge extends Model
{
    /** @use HasFactory<ExamSubjectMergeFactory> */
    use HasFactory;

    use LogsActivity;
    use LogsRelationLabels;
    use MergesTwoSubjects;

    protected $fillable = [
        'exam_id',
        'name',
        'name_bn',
        'first_subject_id',
        'second_subject_id',
        'pass_rule',
        'component_pass_rule',
        'absent_rule',
        'rounding',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * The merges that apply to a student of the given group: both papers are
     * in that group's syllabus. A student who takes only one of the papers
     * (Arabic 1st for every group, Arabic 2nd only for Humanities) has that
     * paper counted as a subject of its own, so the missing paper is never
     * treated as an absence.
     *
     * @param  Collection<int, ExamSubjectMerge>  $merges
     * @param  Collection<int, Collection<int, ClassGroupSubject>>  $subjectTypeRecords  from ClassGroupSubject::recordsBySubjectForClass()
     * @return Collection<int, ExamSubjectMerge>
     */
    public static function applyingToGroup(Collection $merges, Collection $subjectTypeRecords, ?int $groupId): Collection
    {
        return $merges
            ->filter(fn (ExamSubjectMerge $merge): bool => collect($merge->subjectIds())->every(
                fn (int $subjectId): bool => ClassGroupSubject::isTakenByGroup($subjectTypeRecords->get($subjectId), $groupId)
            ))
            ->values();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('exam_subject_merge')
            ->setDescriptionForEvent(function (string $eventName): string {
                $examLabel = Exam::activityLabelFor($this->exam_id) ?? "Exam #{$this->exam_id}";

                return ucfirst($eventName)." subject merge \"{$this->name}\" in \"{$examLabel}\".";
            });
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_id' => fn (int|string|null $id): ?string => Exam::activityLabelFor($id),
            'first_subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
            'second_subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
        ];
    }
}
