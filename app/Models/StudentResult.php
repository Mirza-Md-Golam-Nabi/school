<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentResult extends Model
{
    protected $fillable = [
        'exam_id',
        'subject_id',
        'class_id',
        'student_id',
        'mcq_marks',
        'written_marks',
        'practical_marks',
        'total_marks',
        'is_absent',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
        'mcq_marks' => 'float',
        'written_marks' => 'float',
        'practical_marks' => 'float',
        'total_marks' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (StudentResult $result): void {
            if ($result->is_absent) {
                $result->mcq_marks = null;
                $result->written_marks = null;
                $result->practical_marks = null;
                $result->total_marks = 0;
            } else {
                $result->total_marks = round(
                    (float) ($result->mcq_marks ?? 0)
                        + (float) ($result->written_marks ?? 0)
                        + (float) ($result->practical_marks ?? 0),
                    2
                );
            }
        });
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }
}
