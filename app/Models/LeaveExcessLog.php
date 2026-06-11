<?php

namespace App\Models;

use Database\Factories\LeaveExcessLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeaveExcessLog extends Model
{
    /** @use HasFactory<LeaveExcessLogFactory> */
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'applicant_type',
        'applicant_id',
        'allowed_days',
        'taken_days',
        'excess_days',
        'consequence_applied',
    ];

    protected $casts = [
        'consequence_applied' => 'boolean',
    ];

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function applicant(): MorphTo
    {
        return $this->morphTo();
    }
}
