<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\Religion;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'gender',
        'date_of_birth',
        'blood_group',
        'religion',
        'nationality',
        'designation',
        'joining_date',
        'status',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'blood_group' => BloodGroup::class,
        'religion' => Religion::class,
        'status' => EmploymentStatus::class,
        'date_of_birth' => 'date',
        'joining_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (StaffProfile $profile) {
            if (! $profile->isForceDeleting()) {
                $profile->status = EmploymentStatus::Terminated;
                $profile->saveQuietly();
            }
        });

        static::restored(function (StaffProfile $profile) {
            $profile->status = EmploymentStatus::Active;
            $profile->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function leaveApplications(): MorphMany
    {
        return $this->morphMany(LeaveApplication::class, 'applicant');
    }

    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', EmploymentStatus::Active);
    }
}
