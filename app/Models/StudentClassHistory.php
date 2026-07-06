<?php

namespace App\Models;

use App\Enums\PromotionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentClassHistory extends Model
{
    protected $table = 'student_class_history';

    protected $fillable = [
        'student_id',
        'class_id',
        'section_id',
        'group_id',
        'roll_no',
        'session_year',
        'status',
        'promoted_by',
        'remarks',
    ];

    protected $casts = [
        'status' => PromotionStatus::class,
        'session_year' => 'integer',
        'roll_no' => 'integer',
    ];

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function promotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }
}
