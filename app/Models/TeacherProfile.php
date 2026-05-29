<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\TeacherStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'gender',
        'date_of_birth',
        'blood_group',
        'religion',
        'nationality',
        'designation',
        'department',
        'qualification',
        'joining_date',
        'status',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'blood_group' => BloodGroup::class,
        'religion' => Religion::class,
        'status' => TeacherStatus::class,
        'date_of_birth' => 'date',
        'joining_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TeacherProfile $profile) {
            if (! $profile->isForceDeleting()) {
                $profile->status = TeacherStatus::Terminated;
                $profile->saveQuietly();
            }
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
}
