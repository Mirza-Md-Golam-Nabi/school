<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmitCard extends Model
{
    protected $fillable = [
        'student_id',
        'exam_id',
        'is_generated',
        'page_size',
        'generated_by',
        'generated_at',
        'file_path',
        'file_generated_at',
    ];

    protected $casts = [
        'is_generated' => 'boolean',
        'generated_at' => 'datetime',
        'file_generated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
