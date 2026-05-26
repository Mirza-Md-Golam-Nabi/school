<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamSubjectConfig extends Model
{
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
    ];

    protected $casts = [
        'check_mcq_pass' => 'boolean',
        'check_written_pass' => 'boolean',
        'check_practical_pass' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
