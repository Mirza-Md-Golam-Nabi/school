<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMeritRanking extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'class_id',
        'section_id',
        'total_marks',
        'gpa',
        'class_rank',
        'section_rank',
    ];

    protected $casts = [
        'total_marks' => 'float',
        'gpa' => 'float',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
