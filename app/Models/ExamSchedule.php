<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Database\Factories\ExamScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExamSchedule extends Model
{
    /** @use HasFactory<ExamScheduleFactory> */
    use HasFactory;

    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'exam_id',
        'subject_id',
        'exam_date',
    ];

    protected $casts = [
        'exam_date' => 'date:Y-m-d',
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
            ->useLogName('exam_schedule')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_id' => fn (int|string|null $id): ?string => $id === null ? null : Exam::withTrashed()->with(['examType', 'class'])->find($id)?->displayLabel(),
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : Subject::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $subjectLabel = $this->subject?->name ?? "Subject #{$this->subject_id}";
        $examLabel = Exam::withTrashed()->with(['examType', 'class'])->find($this->exam_id)?->displayLabel() ?? "Exam #{$this->exam_id}";

        return ucfirst($eventName)." exam schedule for \"{$subjectLabel}\" in \"{$examLabel}\".";
    }
}
