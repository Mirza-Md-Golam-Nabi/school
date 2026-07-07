<?php

namespace App\Models;

use App\Models\StudentMeritRanking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
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
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function results(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }

    public function meritRankings(): HasMany
    {
        return $this->hasMany(StudentMeritRanking::class);
    }
}
