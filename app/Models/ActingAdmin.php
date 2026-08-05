<?php

namespace App\Models;

use App\Enums\ActingAdminLevel;
use App\Enums\EmploymentStatus;
use Database\Factories\ActingAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ActingAdmin extends Model
{
    /** @use HasFactory<ActingAdminFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assigned_by',
        'from_date',
        'to_date',
        'level',
        'is_active',
        'remarks',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'level' => ActingAdminLevel::class,
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * A null to_date means no end date — active permanently until manually removed.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active
        && $this->from_date->lte(Carbon::today())
        && ($this->to_date === null || $this->to_date->gte(Carbon::today()));
    }

    public static function activeUser()
    {
        $teachers = TeacherProfile::with('user')
            ->where('status', EmploymentStatus::Active)
            ->get()
            ->map(fn ($profile) => [
                'id' => $profile->user_id,
                'name' => $profile->user->name,
                'type' => 'Teacher',
            ]);

        $staff = StaffProfile::with('user')
            ->where('status', EmploymentStatus::Active)
            ->get()
            ->map(fn ($profile) => [
                'id' => $profile->user_id,
                'name' => $profile->user->name,
                'type' => 'Staff',
            ]);

        return $teachers->concat($staff)
            ->sortBy('name')
            ->values()
            ->mapWithKeys(fn ($person) => [
                $person['id'] => $person['name'].' ('.$person['type'].')',
            ])
            ->toArray();
    }
}
