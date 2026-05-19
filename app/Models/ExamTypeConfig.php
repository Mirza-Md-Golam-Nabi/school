<?php

namespace App\Models;

use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamTypeConfig extends Model
{
    protected $fillable = [
        'exam_type_id',
        'type',
        'count_method',
        'best_n_count',
    ];

    protected $casts = [
        'type' => ExamConfigType::class,
        'count_method' => CountMethod::class,
    ];

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }
}
