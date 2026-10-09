<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExamSubjectConfig extends Model
{
    use LogsActivity;
    use LogsRelationLabels;
    use SoftDeletes;

    protected $fillable = [
        'exam_id',
        'subject_id',
        'mcq_total',
        'mcq_pass_mark',
        'written_total',
        'written_pass_mark',
        'practical_total',
        'practical_pass_mark',
        'total_marks',
        'pass_mark',
        'check_mcq_pass',
        'check_written_pass',
        'check_practical_pass',
        'contributes_to_target',
    ];

    protected $casts = [
        'check_mcq_pass' => 'boolean',
        'check_written_pass' => 'boolean',
        'check_practical_pass' => 'boolean',
        'contributes_to_target' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('exam_subject_config')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_id' => fn (int|string|null $id): ?string => Exam::activityLabelFor($id),
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $subjectLabel = self::activityNameLabel(Subject::class, $this->subject_id) ?? "Subject #{$this->subject_id}";
        $examLabel = Exam::activityLabelFor($this->exam_id) ?? "Exam #{$this->exam_id}";

        return ucfirst($eventName)." subject config for \"{$subjectLabel}\" in \"{$examLabel}\".";
    }
}
