<?php

namespace App\Models;

use App\Models\StudentMeritRanking;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use LogsActivity;
    use LogsRelationLabels;
    use SoftDeletes;

    protected $fillable = [
        'exam_type_id',
        'class_id',
        'session_year',
        'start_date',
        'end_date',
        'is_published',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_published' => 'boolean',
        'session_year' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Exam $exam): void {
            $exam->subjectConfigs()->delete();
        });

        static::restoring(function (Exam $exam): void {
            $exam->subjectConfigs()->withTrashed()->restore();
        });
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subjectConfigs(): HasMany
    {
        return $this->hasMany(ExamSubjectConfig::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }

    public function meritRankings(): HasMany
    {
        return $this->hasMany(StudentMeritRanking::class);
    }

    public function displayLabel(): string
    {
        return "{$this->examType?->name} - {$this->class?->name} ({$this->session_year})";
    }

    /**
     * displayLabel() of an exam (trashed included), resolved once per request
     * for the activity log.
     */
    public static function activityLabelFor(int|string|null $examId): ?string
    {
        if ($examId === null) {
            return null;
        }

        return self::cachedActivityLabel(
            "exam_label|{$examId}",
            fn (): ?string => self::withTrashed()->with(['examType', 'class'])->find($examId)?->displayLabel(),
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('exam')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." exam \"{$this->displayLabel()}\".");
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'exam_type_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(ExamType::class, $id),
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
        ];
    }
}
