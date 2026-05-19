<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamContributeRule extends Model
{
    protected $fillable = [
        'class_id',
        'source_exam_type_id',
        'target_exam_type_id',
        'contribution_percent',
        'session_year',
    ];

    protected $casts = [
        'contribution_percent' => 'integer',
        'session_year' => 'integer',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function sourceExamType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'source_exam_type_id');
    }

    public function targetExamType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'target_exam_type_id');
    }
}
