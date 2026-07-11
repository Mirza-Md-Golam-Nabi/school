<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceStatusChange extends Model
{
    protected $fillable = [
        'attendance_id',
        'class_id',
        'student_profile_id',
        'date',
        'old_status',
        'new_status',
        'changed_by',
        'batch_id',
    ];

    protected $casts = [
        'date' => 'date',
        'old_status' => AttendanceStatus::class,
        'new_status' => AttendanceStatus::class,
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
