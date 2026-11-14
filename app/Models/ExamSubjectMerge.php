<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use App\Traits\MergesTwoSubjects;
use Database\Factories\ExamSubjectMergeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
