<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeDiscount extends Model
{
    protected $fillable = [
        'student_id',
        'fee_type_id',
        'discount_id',
        'session_year',
        'approved_by',
        'remarks',
    ];

    protected $casts = [
        'session_year' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(FeeDiscount::class, 'discount_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
